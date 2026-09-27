<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\RawEventStatus;
use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\EmployeeWorkSchedule;
use App\Models\Hr\Holiday;
use App\Models\Hr\WorkSchedule;
use App\Models\User;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class AttendanceProcessingTest extends TestCase
{
    use AttendanceTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(CarbonImmutable::parse('2026-09-23 12:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_device_punches_become_an_attendance_record(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:45:10'), 'employee_identifier' => '1001', 'external_id' => '1'],
            ['time' => $this->local('12:02'), 'employee_identifier' => '1001', 'external_id' => '2'], // lunch exit, ignored for in/out
            ['time' => $this->local('17:20'), 'employee_identifier' => '1001', 'external_id' => '3'],
        ]);

        $result = app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday)->subDay(), CarbonImmutable::now());
        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['stored']);

        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame($this->monday, $record->date->format('Y-m-d'));
        $this->assertSame(AttendanceStatus::Late, $record->status);
        $this->assertSame(5, $record->late_minutes);
        $this->assertSame(10, $record->early_leave_minutes);
        $this->assertSame(514 - 60, $record->worked_minutes); // 08:45:10 → 17:20 (truncated) minus break
        $this->assertSame('03:45:10', $record->actual_check_in->format('H:i:s')); // stored in UTC
        $this->assertSame($device->id, $record->device_id);
        $this->assertSame('biometric', $record->source->value);
    }

    public function test_unknown_employee_raw_event_is_retained_as_unmatched(): void
    {
        [$organization] = $this->createTenant();
        $device = $this->fakeDevice($organization, [['time' => $this->local('08:30'), 'employee_identifier' => '4242', 'external_id' => 'u1']]);

        $result = app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $this->assertSame(1, $result['unmatched']);
        $event = AttendanceRawEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(RawEventStatus::Unmatched, $event->processing_status);
        $this->assertSame('4242', $event->employee_identifier);
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->count());

        // Mapping the identifier later and reprocessing attaches the punch.
        $employee = $this->mappedEmployee($organization, '4242');
        [, $owner] = [$organization, User::query()->where('organization_id', $organization->id)->first()];
        $this->actingAsUser($owner)->postJson('/api/v1/attendance-raw-events/reprocess')->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }

    public function test_identifier_is_not_assumed_to_be_employee_id(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '9001');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:30'), 'employee_identifier' => (string) $employee->id, 'external_id' => 'x'],
        ]);

        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->count());
    }

    public function test_card_number_mapping_is_supported(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1001');
        $this->inTenant($organization, fn () => $employee->deviceIdentifiers()->update(['card_number' => 'CARD-77']));
        $device = $this->fakeDevice($organization, [['time' => $this->local('08:30'), 'card_number' => 'CARD-77', 'external_id' => 'c']]);

        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }

    public function test_close_day_marks_absent_and_incomplete_idempotently(): void
    {
        [$organization] = $this->createTenant();
        $absent = $this->mappedEmployee($organization, '1');
        $forgotToCheckOut = $this->mappedEmployee($organization, '2');
        $onLeave = $this->mappedEmployee($organization, '3', ['status' => 'on_leave']);
        $device = $this->fakeDevice($organization, [['time' => $this->local('08:20'), 'employee_identifier' => '2', 'external_id' => 'a']]);

        // Punch processed while the day is still running: "currently working".
        Date::setTestNow(CarbonImmutable::parse($this->monday.' 12:00', 'Asia/Tashkent'));
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());
        $this->assertSame(AttendanceStatus::Present, $this->record($forgotToCheckOut->id)->status);

        // Next day the day is closed.
        Date::setTestNow(CarbonImmutable::parse('2026-09-22 01:00', 'Asia/Tashkent'));
        $processor = app(AttendanceProcessor::class);
        $stats = $processor->closeDay($organization, CarbonImmutable::parse($this->monday));
        $again = $processor->closeDay($organization, CarbonImmutable::parse($this->monday));

        $this->assertSame(['absent' => 1, 'incomplete' => 1, 'on_leave' => 1], $stats);
        $this->assertSame(['absent' => 0, 'incomplete' => 0, 'on_leave' => 0], $again);
        $this->assertSame(AttendanceStatus::Absent, $this->record($absent->id)->status);
        $this->assertSame(AttendanceStatus::Incomplete, $this->record($forgotToCheckOut->id)->status);
        $this->assertSame(AttendanceStatus::OnLeave, $this->record($onLeave->id)->status);
    }

    public function test_weekends_and_holidays_are_not_marked_absent(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1');
        $this->inTenant($organization, fn () => Holiday::query()->create(['date' => $this->monday, 'name' => 'Test holiday']));
        $processor = app(AttendanceProcessor::class);

        $processor->closeDay($organization, CarbonImmutable::parse($this->monday));   // holiday
        $processor->closeDay($organization, CarbonImmutable::parse('2026-09-20'));    // Sunday

        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }

    public function test_overnight_shift_punches_belong_to_the_start_date(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '7');
        $this->inTenant($organization, function () use ($employee) {
            $night = WorkSchedule::query()->create(['name' => 'Night', 'type' => 'shift', 'grace_minutes' => 0, 'status' => 'active']);
            foreach (range(1, 7) as $d) {
                $night->days()->create(['day_of_week' => $d, 'is_working_day' => true, 'start_time' => '22:00', 'end_time' => '06:00']);
            }
            EmployeeWorkSchedule::query()->create(['employee_id' => $employee->id, 'work_schedule_id' => $night->id, 'effective_from' => '2025-01-01']);
        });

        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('22:10'), 'employee_identifier' => '7', 'external_id' => 'n1'],
            ['time' => $this->local('06:05', '2026-09-22'), 'employee_identifier' => '7', 'external_id' => 'n2'],
        ]);
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $records = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
        $this->assertCount(1, $records);
        $this->assertSame($this->monday, $records[0]->date->format('Y-m-d'));
        $this->assertSame(10, $records[0]->late_minutes);
        $this->assertSame(475, $records[0]->worked_minutes);
    }

    public function test_manual_creation_and_correction_are_audited(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1');
        $this->actingAsUser($owner);

        $id = $this->postJson('/api/v1/attendance', [
            'employee_id' => $employee->id, 'date' => $this->monday, 'check_in' => '2026-09-21 08:50', 'check_out' => '2026-09-21 17:30', 'reason' => 'Device was offline',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.late_minutes', 10)
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.edit_reason', 'Device was offline')
            ->json('data.id');

        $this->putJson("/api/v1/attendance/{$id}", ['check_in' => '2026-09-21 08:30'])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->putJson("/api/v1/attendance/{$id}", ['check_in' => '2026-09-21 08:30', 'reason' => 'Employee showed badge photo'])
            ->assertOk()->assertJsonPath('data.status', 'present')->assertJsonPath('data.late_minutes', 0);

        $logs = AttendanceAuditLog::withoutGlobalScopes()->where('attendance_record_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $logs->pluck('action')->all());
        $this->assertSame($owner->id, $logs[1]->user_id);
        $this->assertSame('Employee showed badge photo', $logs[1]->reason);
        $this->assertArrayHasKey('actual_check_in', $logs[1]->old_values);
        $this->assertStringContainsString('03:50', $logs[1]->old_values['actual_check_in']);

        // A second record for the same day is refused.
        $this->postJson('/api/v1/attendance', ['employee_id' => $employee->id, 'date' => $this->monday, 'reason' => 'dup'])->assertStatus(422);
    }

    public function test_biometric_sync_never_overwrites_a_manual_correction(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1');
        $this->actingAsUser($owner)->postJson('/api/v1/attendance', [
            'employee_id' => $employee->id, 'date' => $this->monday, 'check_in' => '2026-09-21 08:30', 'check_out' => '2026-09-21 17:30', 'reason' => 'HR entry',
        ])->assertCreated();

        $device = $this->fakeDevice($organization, [['time' => $this->local('09:40'), 'employee_identifier' => '1', 'external_id' => 'late']]);
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());

        $record = $this->record($employee->id);
        $this->assertSame('03:30', $record->actual_check_in->format('H:i'));
        $this->assertSame(AttendanceStatus::Present, $record->status);
    }

    public function test_monitor_calendar_and_dashboard_endpoints(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->mappedEmployee($organization, '1');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:45'), 'employee_identifier' => '1', 'external_id' => 'a'],
            ['time' => $this->local('17:30'), 'employee_identifier' => '1', 'external_id' => 'b'],
        ]);
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());
        $this->actingAsUser($owner);

        $monitor = $this->getJson('/api/v1/attendance/monitor?date='.$this->monday)->assertOk();
        $monitor->assertJsonPath('meta.summary.present', 1)->assertJsonPath('meta.summary.late', 1)->assertJsonPath('data.0.live_status', 'left');

        $days = collect($this->getJson("/api/v1/attendance/calendar?month=2026-09&employee_id={$employee->id}")->assertOk()->json('data.days'))->keyBy('date');
        $this->assertSame('L', $days[$this->monday]['code']);
        $this->assertSame('W', $days['2026-09-20']['code']);
        $this->assertSame('A', $days['2026-09-22']['code']); // past working day without punches

        $this->getJson('/api/v1/attendance/dashboard')->assertOk()->assertJsonStructure(['data' => ['summary', 'trend', 'by_department', 'live']]);
        $this->getJson('/api/v1/reports/attendance/daily?date='.$this->monday)->assertOk()->assertJsonPath('data.rows.0.late_minutes', 5);
        $this->getJson('/api/v1/reports/attendance/monthly?month=2026-09')->assertOk()->assertJsonPath('data.rows.0.late_count', 1);
        $this->get('/api/v1/reports/attendance/monthly?month=2026-09&export=xlsx')->assertOk()->assertDownload();
        $this->get('/api/v1/reports/attendance/daily?date='.$this->monday.'&export=pdf')->assertOk();
    }

    public function test_employee_sees_only_own_attendance(): void
    {
        [$organization] = $this->createTenant();
        $user = $this->userWithRole($organization, 'employee');
        $me = $this->mappedEmployee($organization, '1', ['user_id' => $user->id]);
        $colleague = $this->mappedEmployee($organization, '2');
        $device = $this->fakeDevice($organization, [
            ['time' => $this->local('08:30'), 'employee_identifier' => '1', 'external_id' => 'a'],
            ['time' => $this->local('08:30'), 'employee_identifier' => '2', 'external_id' => 'b'],
        ]);
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());
        $this->actingAsUser($user);

        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.employee_id', $me->id);
        $this->getJson('/api/v1/attendance/'.$this->record($colleague->id)->id)->assertForbidden();
        $this->getJson('/api/v1/attendance/monitor')->assertForbidden();
        $this->getJson('/api/v1/attendance/calendar?month=2026-09')->assertOk()->assertJsonPath('data.employee.id', $me->id);
    }

    private function record(int $employeeId): AttendanceRecord
    {
        return AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employeeId)->firstOrFail();
    }
}
