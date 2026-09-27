<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class AttendanceTenantIsolationTest extends TestCase
{
    use AttendanceTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(CarbonImmutable::parse('2026-09-22 12:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_same_device_identifier_in_two_tenants_resolves_within_its_own_tenant(): void
    {
        [$orgA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $employeeA = $this->mappedEmployee($orgA, '1001');
        $employeeB = $this->mappedEmployee($orgB, '1001');
        $deviceB = $this->fakeDevice($orgB, [['time' => $this->local('08:30'), 'employee_identifier' => '1001', 'external_id' => 'x']]);

        app(AttendanceSyncService::class)->sync($deviceB, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeA->id)->count());
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeB->id)->count());
        $this->assertSame($orgB->id, AttendanceRawEvent::withoutGlobalScopes()->first()->organization_id);
    }

    public function test_attendance_endpoints_do_not_expose_other_tenant(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $employeeB = $this->mappedEmployee($orgB, '1');
        $deviceB = $this->fakeDevice($orgB, [['time' => $this->local('08:30'), 'employee_identifier' => '1', 'external_id' => 'x'], ['time' => $this->local('08:31'), 'employee_identifier' => '999', 'external_id' => 'y']]);
        app(AttendanceSyncService::class)->sync($deviceB, CarbonImmutable::parse($this->monday), CarbonImmutable::now());
        app(AttendanceProcessor::class)->closeDay($orgB, CarbonImmutable::parse($this->monday));
        $recordB = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeB->id)->firstOrFail();

        $this->actingAsUser($ownerA);
        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/attendance/{$recordB->id}")->assertNotFound();
        $this->putJson("/api/v1/attendance/{$recordB->id}", ['check_in' => '2026-09-21 09:00', 'reason' => 'x'])->assertNotFound();
        $this->getJson('/api/v1/attendance/monitor?date='.$this->monday)->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('meta.summary.present', 0);
        $this->getJson('/api/v1/attendance-devices')->assertOk()->assertJsonCount(1 - 1, 'data');
        $this->getJson("/api/v1/attendance-devices/{$deviceB->id}")->assertNotFound();
        $this->postJson("/api/v1/attendance-devices/{$deviceB->id}/sync")->assertNotFound();
        $this->getJson('/api/v1/attendance-raw-events?status=unmatched')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/reports/attendance/daily?date='.$this->monday)->assertOk()->assertJsonCount(0, 'data.rows');
        $this->postJson('/api/v1/attendance', ['employee_id' => $employeeB->id, 'date' => $this->monday, 'reason' => 'x'])->assertStatus(422);

        $csv = $this->get('/api/v1/reports/attendance/daily?date='.$this->monday.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString($employeeB->last_name, $csv);
    }

    public function test_bridge_token_can_only_push_into_its_own_device_and_tenant(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $employeeA = $this->mappedEmployee($orgA, '5');
        $employeeB = $this->mappedEmployee($orgB, '5');
        $bridgeA = $this->fakeDevice($orgA, [], ['connection_mode' => 'bridge']);

        $token = $this->actingAsUser($ownerA)->postJson("/api/v1/attendance-devices/{$bridgeA->id}/bridge-token")->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/device-bridge/events', ['events' => [
            ['time' => '2026-09-21T08:30:00+05:00', 'employee_identifier' => '5', 'external_id' => '100'],
            ['time' => '2026-09-21T17:30:00+05:00', 'employee_identifier' => '5', 'external_id' => '101'],
        ]])->assertOk()->assertJsonPath('data.stored', 2)->assertJsonPath('data.processed', 2);

        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeA->id)->count());
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeB->id)->count());

        $this->withToken('hbr_invalid')->postJson('/api/v1/device-bridge/events', ['events' => []])->assertUnauthorized();
        $this->withToken($ownerA->createToken('x')->plainTextToken)->postJson('/api/v1/device-bridge/events', ['events' => []])->assertUnauthorized();
    }
}
