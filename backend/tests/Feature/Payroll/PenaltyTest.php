<?php

namespace Tests\Feature\Payroll;

use App\Enums\PenaltyStatus;
use App\Models\Attendance\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Payroll\Penalty;
use App\Models\Payroll\PenaltyRule;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\Feature\Attendance\AttendanceTestHelpers;
use Tests\TestCase;

class PenaltyTest extends TestCase
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

    public function test_tiered_late_rules_apply_the_most_specific_rule_only(): void
    {
        [$organization] = $this->createTenant();
        $this->rules($organization);
        $slightly = $this->mappedEmployee($organization, '1');
        $very = $this->mappedEmployee($organization, '2');
        $ok = $this->mappedEmployee($organization, '3');
        $this->punch($organization, [['1', '09:05'], ['2', '09:50'], ['3', '08:38']]); // late 15, 60, 0 (grace 10)

        $this->assertEquals([['late', 20000.0]], $this->penaltiesOf($slightly->id));
        $this->assertEquals([['late', 50000.0]], $this->penaltiesOf($very->id));
        $this->assertSame([], $this->penaltiesOf($ok->id));
    }

    public function test_per_minute_and_percentage_calculations(): void
    {
        [$organization] = $this->createTenant();
        $this->inTenant($organization, function () {
            PenaltyRule::query()->create(['name' => 'Per minute', 'type' => 'late', 'min_minutes' => 1, 'calculation' => 'per_minute', 'amount' => 1000, 'is_active' => true]);
            PenaltyRule::query()->create(['name' => 'Half day', 'type' => 'early_leave', 'min_minutes' => 60, 'calculation' => 'percent_of_daily', 'percentage' => 50, 'is_active' => true]);
        });
        $employee = $this->mappedEmployee($organization, '1', ['salary_type' => 'monthly', 'salary_amount' => 6600000]);
        $this->punch($organization, [['1', '09:00'], ['1', '16:00']]); // late 20, early 90

        // September 2026 has 22 working days → daily rate 300 000.
        $this->assertEquals([['early_leave', 150000.0], ['late', 20000.0]], $this->penaltiesOf($employee->id));
    }

    public function test_absence_deducts_daily_salary(): void
    {
        [$organization] = $this->createTenant();
        $this->rules($organization);
        $employee = $this->mappedEmployee($organization, '1', ['salary_type' => 'monthly', 'salary_amount' => 6600000]);

        app(AttendanceProcessor::class)->closeDay($organization, CarbonImmutable::parse($this->monday));

        $this->assertEquals([['absence', 300000.0]], $this->penaltiesOf($employee->id));
    }

    public function test_correction_removes_pending_penalty_but_keeps_approved(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->rules($organization);
        $employee = $this->mappedEmployee($organization, '1');
        $this->punch($organization, [['1', '09:05'], ['1', '17:30']]);
        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
        $this->actingAsUser($owner);

        $this->putJson("/api/v1/attendance/{$record->id}", ['check_in' => '2026-09-21 08:30', 'reason' => 'Traffic accident, approved'])->assertOk();
        $this->assertSame([], $this->penaltiesOf($employee->id));

        // Late again, approve, then correct: the approved penalty stays.
        $this->putJson("/api/v1/attendance/{$record->id}", ['check_in' => '2026-09-21 09:05', 'reason' => 'Revert'])->assertOk();
        $penalty = Penalty::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
        $this->postJson("/api/v1/penalties/{$penalty->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->putJson("/api/v1/attendance/{$record->id}", ['check_in' => '2026-09-21 08:30', 'reason' => 'Again'])->assertOk();

        $this->assertSame(PenaltyStatus::Approved, $penalty->fresh()->status);
    }

    public function test_manual_penalty_and_approval_workflow(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization);
        $hr = $this->userWithRole($organization, 'hr_manager');
        $sales = $this->userWithRole($organization, 'sales_manager');

        $id = $this->actingAsUser($hr)->postJson('/api/v1/penalties', [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'amount' => 75000, 'reason' => 'Damaged equipment',
        ])->assertCreated()->assertJsonPath('data.type', 'manual')->assertJsonPath('data.status', 'pending')->json('data.id');

        $this->actingAsUser($sales)->postJson("/api/v1/penalties/{$id}/approve")->assertForbidden();
        $this->actingAsUser($hr)->postJson("/api/v1/penalties/{$id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson("/api/v1/penalties/{$id}/approve")->assertStatus(422);

        $this->getJson('/api/v1/penalties?status=rejected')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('event', 'penalty.rejected')->exists());
    }

    public function test_auto_approve_setting(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->rules($organization);
        $this->actingAsUser($owner)->putJson('/api/v1/settings/organization', ['settings' => ['penalties' => ['auto_approve' => true]]])->assertOk();
        $employee = $this->mappedEmployee($organization, '1');
        $this->punch($organization, [['1', '09:30']]);

        $this->assertSame(PenaltyStatus::Approved, Penalty::withoutGlobalScopes()->where('employee_id', $employee->id)->first()->status);
    }

    public function test_rules_are_configurable_via_api(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner)->postJson('/api/v1/penalty-rules', [
            'name' => 'Late 30+', 'type' => 'late', 'min_minutes' => 30, 'calculation' => 'fixed', 'amount' => 40000,
        ])->assertCreated()->assertJsonPath('data.amount', 40000);

        $this->postJson('/api/v1/penalty-rules', ['name' => 'Bad', 'type' => 'late', 'min_minutes' => 30, 'max_minutes' => 10, 'calculation' => 'fixed', 'amount' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['max_minutes']);
    }

    private function rules($organization): void
    {
        $this->inTenant($organization, function () {
            PenaltyRule::query()->create(['name' => 'Late 15+', 'type' => 'late', 'min_minutes' => 15, 'max_minutes' => 59, 'calculation' => 'fixed', 'amount' => 20000, 'is_active' => true]);
            PenaltyRule::query()->create(['name' => 'Late 60+', 'type' => 'late', 'min_minutes' => 60, 'calculation' => 'fixed', 'amount' => 50000, 'is_active' => true]);
            PenaltyRule::query()->create(['name' => 'Absence', 'type' => 'absence', 'min_minutes' => 0, 'calculation' => 'daily_salary', 'amount' => 0, 'is_active' => true]);
            PenaltyRule::query()->create(['name' => 'Inactive', 'type' => 'late', 'min_minutes' => 1, 'calculation' => 'fixed', 'amount' => 999, 'is_active' => false]);
        });
    }

    /** @param list<array{0: string, 1: string}> $punches identifier, local time */
    private function punch($organization, array $punches): void
    {
        $events = array_map(fn ($p, $i) => ['time' => $this->local($p[1]), 'employee_identifier' => $p[0], 'external_id' => 'p'.$i], $punches, array_keys($punches));
        $device = $this->fakeDevice($organization, $events);
        app(AttendanceSyncService::class)->sync($device, CarbonImmutable::parse($this->monday), CarbonImmutable::now());
    }

    /** @return list<array{0: string, 1: float}> */
    private function penaltiesOf(int $employeeId): array
    {
        return Penalty::withoutGlobalScopes()->where('employee_id', $employeeId)->orderBy('type')->get()
            ->map(fn (Penalty $p) => [$p->type->value, (float) $p->amount])->all();
    }
}
