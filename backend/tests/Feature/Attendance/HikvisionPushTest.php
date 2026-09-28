<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceDevicePushLog;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Terminal → CRM event push (ISAPI listening host). Payload shapes follow the EventNotificationAlert /
 * AccessControllerEvent examples of the ISAPI Developer Guide (Face Recognition Terminals). Confirm against a
 * request captured from the real terminal in the device push log.
 */
class HikvisionPushTest extends TestCase
{
    use AttendanceTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(CarbonImmutable::parse('2026-09-21 20:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_multipart_access_event_is_ingested_and_the_picture_is_not_stored(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        [, $url] = $this->pushDevice($organization);

        $this->call('POST', $url, ['AccessControllerEvent' => json_encode($this->alert(501, '2026-09-21T08:44:10+05:00', '1001'))], [], [
            'Picture' => UploadedFile::fake()->image('face.jpg'),
        ], ['CONTENT_TYPE' => 'multipart/form-data; boundary=MIME_boundary', 'HTTP_ACCEPT' => 'application/json'])->assertOk();

        $raw = AttendanceRawEvent::withoutGlobalScopes()->sole();
        $this->assertSame('1001', $raw->employee_identifier);
        $this->assertSame('sn:501:20260921034410', $raw->external_event_id);
        $this->assertSame('http_push', $raw->payload['source']);

        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->sole();
        $this->assertSame(4, $record->late_minutes); // 08:44 vs 08:30 + 10 min grace

        $log = AttendanceDevicePushLog::withoutGlobalScopes()->sole();
        $this->assertSame('accepted', $log->status);
        $this->assertSame(1, $log->stored_count);
        $this->assertStringContainsString('"employeeNoString":"1001"', $log->body_excerpt);
        $this->assertStringContainsString('not stored', $log->body_excerpt);

        // No picture persisted anywhere.
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_raw_json_body_and_duplicates(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '7');
        [, $url] = $this->pushDevice($organization);
        $body = json_encode($this->alert(9, '2026-09-21T08:30:00+05:00', '7'));

        $this->postRaw($url, $body, 'application/json')->assertOk();
        $this->postRaw($url, $body, 'application/json')->assertOk(); // terminal re-sends the same event

        $this->assertSame(1, AttendanceRawEvent::withoutGlobalScopes()->count());
        $this->assertSame([1, 0], AttendanceDevicePushLog::withoutGlobalScopes()->orderBy('id')->pluck('stored_count')->all());
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }

    public function test_serial_numbers_that_wrap_around_are_not_mistaken_for_duplicates(): void
    {
        [$organization] = $this->createTenant();
        $this->mappedEmployee($organization, '7');
        [, $url] = $this->pushDevice($organization);

        $this->postRaw($url, json_encode($this->alert(1, '2026-09-20T08:30:00+05:00', '7')), 'application/json');
        $this->postRaw($url, json_encode($this->alert(1, '2026-09-21T08:30:00+05:00', '7')), 'application/json');

        $this->assertSame(2, AttendanceRawEvent::withoutGlobalScopes()->count());
    }

    public function test_xml_body_with_namespace(): void
    {
        [$organization] = $this->createTenant();
        $this->mappedEmployee($organization, '1002');
        [, $url] = $this->pushDevice($organization);
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<EventNotificationAlert version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">
  <ipAddress>192.168.1.64</ipAddress>
  <dateTime>2026-09-21T17:31:00+05:00</dateTime>
  <eventType>AccessControllerEvent</eventType>
  <eventState>active</eventState>
  <AccessControllerEvent>
    <majorEventType>5</majorEventType>
    <subEventType>75</subEventType>
    <employeeNoString>1002</employeeNoString>
    <serialNo>77</serialNo>
    <attendanceStatus>checkOut</attendanceStatus>
  </AccessControllerEvent>
</EventNotificationAlert>
XML;

        $this->postRaw($url, $xml, 'application/xml')->assertOk();

        $raw = AttendanceRawEvent::withoutGlobalScopes()->sole();
        $this->assertSame('1002', $raw->employee_identifier);
        $this->assertSame('check_out', $raw->event_type->value);
    }

    public function test_heartbeat_marks_the_device_alive_without_events(): void
    {
        [$organization] = $this->createTenant();
        [$device, $url] = $this->pushDevice($organization);

        $this->postRaw($url, json_encode(['dateTime' => '2026-09-21T09:00:00+05:00', 'eventType' => 'heartBeat', 'eventState' => 'active']), 'application/json')->assertOk();

        $this->assertSame(0, AttendanceRawEvent::withoutGlobalScopes()->count());
        $this->assertSame('heartbeat', AttendanceDevicePushLog::withoutGlobalScopes()->sole()->status);
        $this->assertNotNull($device->fresh()->last_sync_at);
        $this->assertSame('ok', $device->fresh()->last_sync_status);
    }

    public function test_events_without_a_person_are_ignored(): void
    {
        [$organization] = $this->createTenant();
        [, $url] = $this->pushDevice($organization);
        $alert = $this->alert(3, '2026-09-21T09:00:00+05:00', '');
        unset($alert['AccessControllerEvent']['employeeNoString']);

        $this->postRaw($url, json_encode($alert), 'application/json')->assertOk();

        $this->assertSame(0, AttendanceRawEvent::withoutGlobalScopes()->count());
        $this->assertSame('ignored', AttendanceDevicePushLog::withoutGlobalScopes()->sole()->status);
    }

    public function test_unrecognized_payloads_are_kept_for_inspection(): void
    {
        [$organization] = $this->createTenant();
        [, $url] = $this->pushDevice($organization);

        $this->postRaw($url, 'hello from a firmware we do not know', 'text/plain')->assertOk();

        $log = AttendanceDevicePushLog::withoutGlobalScopes()->sole();
        $this->assertSame('unparsed', $log->status);
        $this->assertSame('hello from a firmware we do not know', $log->body_excerpt);
    }

    public function test_tokens_and_device_state_are_enforced(): void
    {
        [$organization, $owner] = $this->createTenant();
        [$device, $url] = $this->pushDevice($organization);
        $body = json_encode($this->alert(1, '2026-09-21T08:30:00+05:00', '1'));

        $this->postRaw('/api/v1/hikvision/push/hpt_'.str_repeat('x', 48), $body, 'application/json')->assertNotFound();

        // A bridge token cannot be used on the push URL and vice versa.
        $bridge = $this->fakeDevice($organization, [], ['connection_mode' => 'bridge']);
        $bridgeToken = $this->actingAsUser($owner)->postJson("/api/v1/attendance-devices/{$bridge->id}/bridge-token")->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->postRaw('/api/v1/hikvision/push/'.$bridgeToken, $body, 'application/json')->assertNotFound();
        $pushToken = basename($url);
        $this->withToken($pushToken)->postJson('/api/v1/device-bridge/events', ['events' => []])->assertUnauthorized();

        $device->update(['status' => 'inactive']);
        $this->postRaw($url, $body, 'application/json')->assertForbidden();
        $this->assertSame('rejected', AttendanceDevicePushLog::withoutGlobalScopes()->latest('id')->first()->status);
        $this->assertSame(0, AttendanceRawEvent::withoutGlobalScopes()->count());
    }

    public function test_push_url_is_issued_once_and_push_logs_are_tenant_scoped(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB, $ownerB] = $this->createTenant('B');
        [$deviceA, $url] = $this->pushDevice($orgA);
        $this->postRaw($url, json_encode(['eventType' => 'heartBeat', 'dateTime' => '2026-09-21T09:00:00+05:00']), 'application/json');

        $this->actingAsUser($ownerA)->getJson('/api/v1/attendance-devices/push-logs')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.device.id', $deviceA->id);
        $this->actingAsUser($ownerB)->getJson('/api/v1/attendance-devices/push-logs')->assertOk()->assertJsonPath('meta.total', 0);

        // Direct devices have no token.
        $direct = $this->fakeDevice($orgB);
        $this->postJson("/api/v1/attendance-devices/{$direct->id}/bridge-token")->assertStatus(422);
    }

    /** @return array{0: AttendanceDevice, 1: string} device and its push path */
    private function pushDevice(Organization $organization): array
    {
        $owner = User::query()->where('organization_id', $organization->id)->firstOrFail();
        $device = $this->inTenant($organization, fn () => AttendanceDevice::query()->create([
            'name' => 'Entrance (push)', 'vendor' => 'hikvision', 'model' => 'DS-K1A340FWX', 'connection_mode' => 'push', 'status' => 'active',
        ]));

        $path = $this->actingAsUser($owner)->postJson("/api/v1/attendance-devices/{$device->id}/bridge-token")
            ->assertOk()->json('data.push_path');
        $this->app['auth']->forgetGuards();

        $this->assertStringStartsWith('/api/v1/hikvision/push/hpt_', $path);

        return [$device, $path];
    }

    private function postRaw(string $url, string $body, string $contentType): TestResponse
    {
        return $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    /** @return array<string, mixed> */
    private function alert(int $serial, string $time, string $employeeNo): array
    {
        return [
            'ipAddress' => '192.168.1.64',
            'portNo' => 80,
            'protocol' => 'HTTP',
            'macAddress' => '01:17:24:45:d9:f4',
            'channelID' => 1,
            'dateTime' => $time,
            'activePostCount' => 1,
            'eventType' => 'AccessControllerEvent',
            'eventState' => 'active',
            'eventDescription' => 'Access Controller Event',
            'AccessControllerEvent' => [
                'deviceName' => 'Entrance',
                'majorEventType' => 5,
                'subEventType' => 75,
                'name' => 'Test Person',
                'cardReaderNo' => 1,
                'employeeNoString' => $employeeNo,
                'serialNo' => $serial,
                'userType' => 'normal',
                'currentVerifyMode' => 'cardOrFace',
                'attendanceStatus' => 'checkIn',
                'mask' => 'no',
                'picturesNumber' => 1,
            ],
        ];
    }
}
