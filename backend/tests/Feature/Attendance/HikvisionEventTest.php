<?php

namespace Tests\Feature\Attendance;

use App\Enums\DeviceEventType;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceRecord;
use App\Models\AuditLog;
use App\Services\Attendance\AttendanceDeviceManager;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\Devices\Hikvision\HikvisionEventMapper;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hikvision adapter against a faked ISAPI. These tests verify our request/response handling;
 * they do NOT claim a physical DS-K1A340FWX was exercised (see docs/HIKVISION.md for on-device verification).
 */
class HikvisionEventTest extends TestCase
{
    use AttendanceTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(CarbonImmutable::parse('2026-09-21 20:00', 'Asia/Tashkent'));
        config(['attendance.hikvision.retries' => 0, 'attendance.hikvision.retry_sleep_ms' => 0]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_connection_test_reads_device_info(): void
    {
        [$organization, $owner] = $this->createTenant();
        $device = $this->hikvision($organization);
        Http::fake(['192.168.1.64/ISAPI/System/deviceInfo' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><DeviceInfo version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><deviceName>Entrance</deviceName><model>DS-K1A340FWX</model><serialNumber>DS-K1A340FWX20240101AAWRF12345678</serialNumber><firmwareVersion>V3.5.0</firmwareVersion></DeviceInfo>',
            200,
            ['Content-Type' => 'application/xml'],
        )]);

        $this->actingAsUser($owner)->postJson("/api/v1/attendance-devices/{$device->id}/test")
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.info.model', 'DS-K1A340FWX');

        $this->assertSame('DS-K1A340FWX20240101AAWRF12345678', $device->fresh()->serial_number);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ISAPI/System/deviceInfo'));
    }

    public function test_authentication_failure_is_reported(): void
    {
        [$organization] = $this->createTenant();
        $device = $this->hikvision($organization);
        Http::fake(['*' => Http::response('', 401)]);

        $result = app(AttendanceDeviceManager::class)->for($device)->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failed', $result->message);
    }

    public function test_events_are_paginated_and_mapped(): void
    {
        [$organization] = $this->createTenant();
        $device = $this->hikvision($organization);
        Http::fake(['192.168.1.64/ISAPI/AccessControl/AcsEvent*' => Http::sequence()
            ->push(['AcsEvent' => ['searchID' => 'x', 'responseStatusStrg' => 'MORE', 'numOfMatches' => 2, 'totalMatches' => 3, 'InfoList' => [
                $this->info(1, '2026-09-21T08:31:05+05:00', '1001', 'checkIn'),
                ['major' => 5, 'minor' => 21, 'time' => '2026-09-21T08:32:00+05:00', 'serialNo' => 2], // door event, no person
            ]]])
            ->push(['AcsEvent' => ['searchID' => 'x', 'responseStatusStrg' => 'OK', 'numOfMatches' => 1, 'totalMatches' => 3, 'InfoList' => [
                $this->info(3, '2026-09-21T17:40:00+05:00', '1001', 'checkOut'),
            ]]]),
        ]);

        $events = iterator_to_array(app(AttendanceDeviceManager::class)->for($device)->getEvents(Carbon::parse('2026-09-21 00:00'), Carbon::parse('2026-09-21 23:59')), false);

        $this->assertCount(2, $events);
        $this->assertSame('1001', $events[0]->employeeIdentifier);
        $this->assertSame('sn:1:20260921033105', $events[0]->externalId); // serial + UTC time (serials wrap at 100000)
        $this->assertSame(DeviceEventType::CheckIn, $events[0]->type);
        $this->assertSame('2026-09-21 03:31:05', $events[0]->time->format('Y-m-d H:i:s')); // UTC
        $this->assertSame(DeviceEventType::CheckOut, $events[1]->type);

        $recorded = Http::recorded()->map(fn ($pair) => $pair[0]->data()['AcsEventCond'] ?? null)->filter()->values();
        $this->assertSame([0, 2], $recorded->pluck('searchResultPosition')->all());
        $this->assertSame(5, $recorded[0]['major']);
        $this->assertSame('2026-09-21T05:00:00+05:00', $recorded[0]['startTime']);
    }

    public function test_full_sync_through_hikvision_creates_attendance(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->hikvision($organization);
        Http::fake(['192.168.1.64/ISAPI/AccessControl/AcsEvent*' => Http::response(['AcsEvent' => [
            'responseStatusStrg' => 'OK',
            'InfoList' => [
                $this->info(10, '2026-09-21T08:44:00+05:00', '1001', 'checkIn'),
                $this->info(11, '2026-09-21T17:31:00+05:00', '1001', 'checkOut'),
            ],
        ]])]);

        $result = app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse('2026-09-21 00:00', 'Asia/Tashkent'), CarbonImmutable::now());

        $this->assertTrue($result['success']);
        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(4, $record->late_minutes);
        $this->assertSame('ok', $device->fresh()->last_sync_status);
        $this->assertNotNull($device->fresh()->last_event_at);
    }

    public function test_device_unavailable_fails_gracefully(): void
    {
        [$organization, $owner] = $this->createTenant();
        $device = $this->hikvision($organization);
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $result = app(AttendanceSyncService::class)->sync($device);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('unreachable', $result['message']);
        $fresh = $device->fresh();
        $this->assertSame('failed', $fresh->last_sync_status);
        $this->assertNotNull($fresh->last_error);

        // The API surfaces it as a normal response, not a 500.
        $this->actingAsUser($owner)->postJson("/api/v1/attendance-devices/{$device->id}/sync")->assertOk()->assertJsonPath('data.success', false);
        $this->artisan('attendance:sync')->assertExitCode(1);
    }

    public function test_push_employee_modifies_when_it_already_exists_on_device(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->hikvision($organization);
        Http::fake([
            '192.168.1.64/ISAPI/AccessControl/UserInfo/Record*' => Http::response(['statusCode' => 6, 'subStatusCode' => 'employeeNoAlreadyExist'], 400),
            '192.168.1.64/ISAPI/AccessControl/UserInfo/Modify*' => Http::response(['statusCode' => 1, 'statusString' => 'OK']),
        ]);

        app(AttendanceDeviceManager::class)->for($device)->pushEmployee($employee);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'UserInfo/Modify') && $r['UserInfo']['employeeNo'] === '1001');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'UserInfo/Delete'));
    }

    public function test_device_password_is_encrypted_and_never_returned(): void
    {
        [$organization, $owner] = $this->createTenant();

        $id = $this->actingAsUser($owner)->postJson('/api/v1/attendance-devices', [
            'name' => 'Gate', 'vendor' => 'hikvision', 'ip_address' => '10.0.0.5', 'port' => 80, 'username' => 'admin', 'password' => 'S3cret!',
        ])->assertCreated()->assertJsonPath('data.has_password', true)->assertJsonMissingPath('data.password')->json('data.id');

        $raw = \DB::table('attendance_devices')->where('id', $id)->value('encrypted_password');
        $this->assertNotSame('S3cret!', $raw);
        $this->assertStringNotContainsString('S3cret', (string) $raw);
        $this->assertSame('S3cret!', AttendanceDevice::withoutGlobalScopes()->find($id)->encrypted_password);

        $logs = AuditLog::withoutGlobalScopes()->where('auditable_type', 'attendance_device')->get();
        $this->assertStringNotContainsString('S3cret', $logs->toJson());
        $this->assertStringNotContainsString('encrypted_password', $logs->toJson());
    }

    public function test_mapper_handles_single_object_info_list_and_missing_offset(): void
    {
        $mapper = new HikvisionEventMapper;
        $list = $mapper->infoList(['AcsEvent' => ['InfoList' => $this->info(5, '2026-09-21T08:00:00', '7', 'undefined')]]);

        $this->assertCount(1, $list);
        $event = $mapper->map($list[0], 'Asia/Tashkent');
        $this->assertSame('03:00', $event->time->format('H:i'));
        $this->assertSame(DeviceEventType::Unknown, $event->type);
    }

    private function hikvision($organization): AttendanceDevice
    {
        return $this->inTenant($organization, fn () => AttendanceDevice::query()->create([
            'name' => 'Entrance', 'vendor' => 'hikvision', 'model' => 'DS-K1A340FWX', 'connection_mode' => 'direct',
            'ip_address' => '192.168.1.64', 'port' => 80, 'protocol' => 'http', 'username' => 'admin', 'encrypted_password' => 'pw', 'status' => 'active',
        ]));
    }

    /** @return array<string, mixed> */
    private function info(int $serial, string $time, string $employeeNo, string $status): array
    {
        return [
            'major' => 5, 'minor' => 75, 'time' => $time, 'employeeNoString' => $employeeNo, 'name' => 'Test Person',
            'serialNo' => $serial, 'attendanceStatus' => $status, 'currentVerifyMode' => 'face', 'userType' => 'normal',
        ];
    }
}
