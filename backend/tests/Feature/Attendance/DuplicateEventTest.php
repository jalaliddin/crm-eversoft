<?php

namespace Tests\Feature\Attendance;

use App\Enums\DeviceEventType;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\Devices\DeviceEvent;
use App\Services\Attendance\RawEventIngestor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DuplicateEventTest extends TestCase
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

    public function test_repeated_sync_stores_each_biometric_event_once(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:30'), 'employee_identifier' => '1001', 'external_id' => 'sn:1'],
            ['time' => $this->local('17:30'), 'employee_identifier' => '1001', 'external_id' => 'sn:2'],
        ]);
        $sync = app(AttendanceSyncService::class);
        $from = CarbonImmutable::parse($this->monday);

        $first = $sync->sync($device, $from, CarbonImmutable::now());
        $second = $sync->sync($device, $from, CarbonImmutable::now());

        $this->assertSame(2, $first['stored']);
        $this->assertSame(0, $second['stored']);
        $this->assertSame(2, $second['duplicates']);
        $this->assertSame(2, AttendanceRawEvent::withoutGlobalScopes()->count());
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }

    public function test_events_without_device_id_are_deduplicated_by_deterministic_hash(): void
    {
        [$organization] = $this->createTenant();
        $device = $this->fakeDevice($organization);
        $event = new DeviceEvent(CarbonImmutable::parse('2026-09-21 03:30:00', 'UTC'), '1001', type: DeviceEventType::CheckIn);
        $same = new DeviceEvent(CarbonImmutable::parse('2026-09-21 08:30:00', 'Asia/Tashkent'), '1001', type: DeviceEventType::CheckIn);

        $this->assertSame($event->dedupKey($device->id), $same->dedupKey($device->id));
        $this->assertStringStartsWith('h:', $event->dedupKey($device->id));

        $ingestor = app(RawEventIngestor::class);
        $result = $ingestor->store($device, [$event, $same]);
        $ingestor->store($device, [$event]);

        $this->assertSame(1, $result['stored']);
        $this->assertSame(1, AttendanceRawEvent::withoutGlobalScopes()->count());
    }

    public function test_same_external_id_on_different_devices_is_not_a_duplicate(): void
    {
        [$organization] = $this->createTenant();
        $a = $this->fakeDevice($organization);
        $b = $this->fakeDevice($organization);
        $event = new DeviceEvent(CarbonImmutable::parse('2026-09-21 03:30'), '1001', externalId: 'sn:1');

        app(RawEventIngestor::class)->store($a, [$event]);
        app(RawEventIngestor::class)->store($b, [$event]);

        $this->assertSame(2, AttendanceRawEvent::withoutGlobalScopes()->count());
    }

    public function test_double_scan_within_the_punch_window_is_one_check_in(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:29:10'), 'employee_identifier' => '1001', 'external_id' => '1'],
            ['time' => $this->local('08:29:40'), 'employee_identifier' => '1001', 'external_id' => '2'],
        ]);

        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertNotNull($record->actual_check_in);
        $this->assertNull($record->actual_check_out);
    }
}
