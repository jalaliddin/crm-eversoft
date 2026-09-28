<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/** Office browser reads the terminal (Eversoft Terminal Bridge extension) and posts events with the user's session. */
class BrowserBridgeTest extends TestCase
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

    public function test_events_from_an_office_browser_are_ingested_and_deduplicated(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->browserDevice($organization);
        $user = $this->userWithPermissions($organization, ['devices.view', 'devices.sync']);
        $batch = ['events' => [[
            'time' => '2026-09-21T08:44:10+05:00', 'employee_identifier' => '1001', 'event_type' => 'check_in', 'external_id' => '501',
            'payload' => ['major' => 5, 'minor' => 75],
        ]]];

        $this->actingAsUser($user)->postJson("/api/v1/attendance-devices/{$device->id}/browser-events", $batch)
            ->assertOk()->assertJsonPath('data.stored', 1);
        $this->postJson("/api/v1/attendance-devices/{$device->id}/browser-events", $batch)
            ->assertOk()->assertJsonPath('data.stored', 0)->assertJsonPath('data.duplicates', 1);

        $this->assertSame('sn:501:20260921034410', AttendanceRawEvent::withoutGlobalScopes()->sole()->external_event_id);
        $this->assertSame(4, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->sole()->late_minutes);
        $this->assertSame('ok', $device->fresh()->last_sync_status);
        $this->assertNotNull($device->fresh()->last_event_at);
    }

    public function test_an_empty_batch_marks_the_device_read_and_an_error_marks_it_failed(): void
    {
        [$organization, $owner] = $this->createTenant();
        $device = $this->browserDevice($organization);

        $this->actingAsUser($owner)->postJson("/api/v1/attendance-devices/{$device->id}/browser-events", ['events' => []])->assertOk();
        $this->assertSame('ok', $device->fresh()->last_sync_status);
        $this->assertNotNull($device->fresh()->last_sync_at);

        $this->postJson("/api/v1/attendance-devices/{$device->id}/browser-events", ['events' => [], 'error' => 'Terminal rejected the credentials (HTTP 401).'])->assertOk();
        $this->assertSame('failed', $device->fresh()->last_sync_status);
        $this->assertSame('Office browser: Terminal rejected the credentials (HTTP 401).', $device->fresh()->last_error);
    }

    public function test_only_browser_devices_of_the_own_tenant_with_sync_permission_accept_events(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $deviceB = $this->browserDevice($orgB);
        $direct = $this->fakeDevice($orgA);
        $viewer = $this->userWithPermissions($orgA, ['devices.view']);
        $batch = ['events' => [['time' => '2026-09-21T08:30:00+05:00', 'employee_identifier' => '1']]];

        $this->actingAsUser($ownerA)->postJson("/api/v1/attendance-devices/{$deviceB->id}/browser-events", $batch)->assertNotFound();
        $this->postJson("/api/v1/attendance-devices/{$direct->id}/browser-events", $batch)->assertStatus(422);

        $deviceA = $this->browserDevice($orgA);
        $this->actingAsUser($viewer)->postJson("/api/v1/attendance-devices/{$deviceA->id}/browser-events", $batch)->assertForbidden();

        $deviceA->update(['status' => 'inactive']);
        $this->actingAsUser($ownerA)->postJson("/api/v1/attendance-devices/{$deviceA->id}/browser-events", $batch)->assertStatus(422);

        $this->assertSame(0, AttendanceRawEvent::withoutGlobalScopes()->count());
    }

    public function test_the_server_never_connects_to_a_browser_device(): void
    {
        [$organization, $owner] = $this->createTenant();

        // A LAN or loopback address is fine: only the office browser opens it.
        $id = $this->actingAsUser($owner)->postJson('/api/v1/attendance-devices', [
            'name' => 'Entrance', 'vendor' => 'hikvision', 'connection_mode' => 'browser', 'ip_address' => '127.0.0.1', 'port' => 8080,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/attendance-devices', [
            'name' => 'Entrance', 'vendor' => 'hikvision', 'connection_mode' => 'direct', 'ip_address' => '127.0.0.1',
        ])->assertUnprocessable()->assertJsonValidationErrors('ip_address');

        $this->postJson("/api/v1/attendance-devices/{$id}/sync")->assertOk()->assertJsonPath('data.message', 'Events arrive by push: processed pending events.');
        $this->postJson("/api/v1/attendance-devices/{$id}/push-employees")->assertStatus(422);
        $this->postJson("/api/v1/attendance-devices/{$id}/bridge-token")->assertStatus(422);
    }

    private function browserDevice(Organization $organization): AttendanceDevice
    {
        return $this->inTenant($organization, fn () => AttendanceDevice::query()->create([
            'name' => 'Entrance (browser)', 'vendor' => 'hikvision', 'model' => 'DS-K1A340FWX', 'connection_mode' => 'browser',
            'ip_address' => '192.168.1.64', 'port' => 80, 'protocol' => 'http', 'status' => 'active',
        ]));
    }
}
