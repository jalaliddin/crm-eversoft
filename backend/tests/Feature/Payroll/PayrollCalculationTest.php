<?php

namespace Tests\Feature\Payroll;

use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\Penalty;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Tests\TestCase;

/** September 2026: 22 working days (Mon–Fri), 480 min/day → 10 560 scheduled minutes. */
class PayrollCalculationTest extends TestCase
{
    public function test_monthly_salary_minus_approved_penalties(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization, ['salary_type' => 'monthly', 'salary_amount' => 6000000, 'hire_date' => '2025-01-01']);
        $this->attendance($organization, $employee, presentDays: 20, absentDays: 2);
        $this->penalty($organization, $employee, 100000, 'approved');
        $this->penalty($organization, $employee, 55555, 'pending'); // not counted

        $record = $this->calculate($owner);

        $this->assertEquals(6000000, $record['base_salary']);
        $this->assertSame(22, $record['scheduled_days']);
        $this->assertSame(20, $record['worked_days']);
        $this->assertSame(2, $record['absence_days']);
        $this->assertEquals(100000, $record['penalty_amount']);
        $this->assertEquals(5900000, $record['net_amount']);
    }

    public function test_monthly_salary_is_prorated_for_mid_month_hire(): void
    {
        [$organization, $owner] = $this->createTenant();
        // Hired Tue 15 Sep → 12 of 22 working days.
        $this->employee($organization, ['salary_type' => 'monthly', 'salary_amount' => 6000000, 'hire_date' => '2026-09-15']);

        $this->assertEquals(round(6000000 * 12 / 22, 2), $this->calculate($owner)['base_salary']);
    }

    public function test_monthly_prorated_by_worked_days_mode(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->actingAsUser($owner)->putJson('/api/v1/settings/organization', ['settings' => ['payroll' => ['monthly_mode' => 'prorated']]])->assertOk();
        $employee = $this->employee($organization, ['salary_type' => 'monthly', 'salary_amount' => 6600000, 'hire_date' => '2025-01-01']);
        $this->attendance($organization, $employee, presentDays: 11);

        $this->assertEquals(3300000, $this->calculate($owner)['base_salary']);
    }

    public function test_daily_rate_times_worked_days(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization, ['salary_type' => 'daily', 'salary_amount' => 300000, 'hire_date' => '2025-01-01']);
        $this->attendance($organization, $employee, presentDays: 10, absentDays: 3);

        $record = $this->calculate($owner);
        $this->assertSame(10, $record['worked_days']);
        $this->assertEquals(3000000, $record['gross_amount']);
    }

    public function test_hourly_rate_times_worked_hours(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization, ['salary_type' => 'hourly', 'salary_amount' => 45000, 'hire_date' => '2025-01-01']);
        $this->attendance($organization, $employee, presentDays: 10, workedMinutes: 480); // 80 h

        $this->assertEquals(3600000, $this->calculate($owner)['gross_amount']);
    }

    public function test_overtime_pay_when_enabled(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->actingAsUser($owner)->putJson('/api/v1/settings/organization', ['settings' => ['payroll' => ['overtime_pay_enabled' => true, 'overtime_rate_multiplier' => 1.5]]])->assertOk();
        $employee = $this->employee($organization, ['salary_type' => 'monthly', 'salary_amount' => 5280000, 'hire_date' => '2025-01-01']);
        $this->attendance($organization, $employee, presentDays: 22, overtimeMinutes: 60);

        // 5 280 000 / 176 h = 30 000/h; 22 h overtime × 1.5 = 990 000.
        $record = $this->calculate($owner);
        $this->assertEquals(990000, $record['overtime_amount']);
        $this->assertEquals(5280000 + 990000, $record['net_amount']);
    }

    public function test_workflow_bonus_approval_lock_and_payment(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization, ['salary_type' => 'monthly', 'salary_amount' => 1000000, 'hire_date' => '2025-01-01']);
        $this->actingAsUser($owner);

        $periodId = $this->postJson('/api/v1/payroll/calculate', ['year' => 2026, 'month' => 9])->assertOk()->assertJsonPath('data.status', 'calculated')->json('data.id');
        $recordId = $this->getJson("/api/v1/payroll/{$periodId}/records")->json('data.0.id');

        $this->putJson("/api/v1/payroll-records/{$recordId}", ['bonus_amount' => 250000])->assertOk()->assertJsonPath('data.net_amount', 1250000);
        // Recalculation keeps the manual bonus.
        $this->postJson('/api/v1/payroll/calculate', ['period_id' => $periodId])->assertOk()->assertJsonPath('data.totals.net', 1250000);

        $this->postJson("/api/v1/payroll/{$periodId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson('/api/v1/payroll/calculate', ['period_id' => $periodId])->assertStatus(422);
        $this->putJson("/api/v1/payroll-records/{$recordId}", ['bonus_amount' => 1])->assertStatus(422);

        $this->postJson("/api/v1/payroll/{$periodId}/pay")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertSame('paid', PayrollRecord::withoutGlobalScopes()->find($recordId)->status->value);

        $this->get("/api/v1/reports/payroll/{$periodId}?export=csv")->assertOk();
        $this->postJson('/api/v1/payroll', ['year' => 2026, 'month' => 9])->assertStatus(422); // duplicate period
    }

    public function test_payroll_permissions_and_tenant_isolation(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [, $ownerB] = $this->createTenant('B');
        $hr = $this->userWithRole($orgA, 'hr_manager');
        $this->employee($orgA, ['salary_amount' => 1000000]);

        $periodId = $this->actingAsUser($hr)->postJson('/api/v1/payroll/calculate', ['year' => 2026, 'month' => 9])->assertOk()->json('data.id');
        $this->postJson("/api/v1/payroll/{$periodId}/approve")->assertForbidden(); // HR can calculate, not approve

        $this->actingAsUser($ownerB)->getJson("/api/v1/payroll/{$periodId}")->assertNotFound();
        $this->getJson("/api/v1/payroll/{$periodId}/records")->assertNotFound();
        $this->getJson('/api/v1/payroll')->assertOk()->assertJsonPath('meta.total', 0);
    }

    private function calculate($owner): array
    {
        $periodId = $this->actingAsUser($owner)->postJson('/api/v1/payroll/calculate', ['year' => 2026, 'month' => 9])->assertOk()->json('data.id');

        return $this->getJson("/api/v1/payroll/{$periodId}/records")->assertOk()->json('data.0');
    }

    private function attendance(Organization $organization, Employee $employee, int $presentDays, int $absentDays = 0, int $workedMinutes = 480, int $overtimeMinutes = 0): void
    {
        $this->inTenant($organization, function () use ($employee, $presentDays, $absentDays, $workedMinutes, $overtimeMinutes) {
            $workdays = collect(CarbonPeriod::create('2026-09-01', '2026-09-30'))->filter(fn ($d) => $d->isWeekday())->values();
            foreach ($workdays->take($presentDays + $absentDays) as $i => $date) {
                $present = $i < $presentDays;
                AttendanceRecord::query()->create([
                    'employee_id' => $employee->id,
                    'date' => $date->format('Y-m-d'),
                    'status' => $present ? 'present' : 'absent',
                    'source' => 'manual',
                    'actual_check_in' => $present ? CarbonImmutable::parse($date->format('Y-m-d').' 03:30') : null,
                    'worked_minutes' => $present ? $workedMinutes : 0,
                    'overtime_minutes' => $present ? $overtimeMinutes : 0,
                ]);
            }
        });
    }

    private function penalty(Organization $organization, Employee $employee, float $amount, string $status): void
    {
        $this->inTenant($organization, fn () => Penalty::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'type' => 'manual', 'amount' => $amount, 'status' => $status, 'reason' => 'Test',
        ]));
    }
}
