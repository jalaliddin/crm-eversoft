<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PenaltyStatus;
use App\Enums\SalaryType;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Models\Payroll\Penalty;
use App\Services\Hr\WorkingTimeService;
use Carbon\CarbonImmutable;

/**
 * Computes one employee's payroll figures for a period. No persistence.
 *
 *  monthly  fixed:    base = salary × (scheduled days while employed ÷ scheduled days in period)
 *           prorated: base = salary × worked days ÷ scheduled days in period
 *  daily    base = daily rate × worked days
 *  hourly   base = hourly rate × worked hours
 *  overtime (if enabled in tenant settings) = hourly equivalent × overtime hours × multiplier
 *           (hourly staff already get paid for those hours, so only the premium part is added)
 *  net      = max(0, base + overtime + bonus − approved penalties)
 */
class PayrollCalculator
{
    public function __construct(private readonly WorkingTimeService $workingTime) {}

    /** @return array<string, mixed> attributes for payroll_records */
    public function calculate(Employee $employee, Organization $organization, CarbonImmutable $start, CarbonImmutable $end, float $bonus = 0.0): array
    {
        $settings = $organization->resolvedSettings()['payroll'];
        $countIncomplete = (bool) $settings['count_incomplete_as_worked'];

        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get(['status', 'worked_minutes', 'overtime_minutes', 'late_minutes', 'early_leave_minutes']);

        $workedStatuses = [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::EarlyLeave];
        if ($countIncomplete) {
            $workedStatuses[] = AttendanceStatus::Incomplete;
        }

        $workedDays = $records->filter(fn ($r) => in_array($r->status, $workedStatuses, true))->count();
        $workedMinutes = (int) $records->sum('worked_minutes');
        $overtimeMinutes = (int) $records->sum('overtime_minutes');
        $lateMinutes = (int) $records->sum('late_minutes');
        $earlyMinutes = (int) $records->sum('early_leave_minutes');
        $absenceDays = $records->where('status', AttendanceStatus::Absent)->count();

        $periodSchedule = $this->workingTime->scheduled($employee, $start, $end, withinEmployment: false);
        $employedSchedule = $this->workingTime->scheduled($employee, $start, $end, withinEmployment: true);

        $salary = (float) $employee->salary_amount;
        $salaryType = $employee->salary_type ?? SalaryType::Monthly;

        $base = match ($salaryType) {
            SalaryType::Monthly => $periodSchedule['days'] === 0 ? 0.0 : (
                $settings['monthly_mode'] === 'prorated'
                    ? $salary * min(1, $workedDays / $periodSchedule['days'])
                    : $salary * ($employedSchedule['days'] / $periodSchedule['days'])
            ),
            SalaryType::Daily => $salary * $workedDays,
            SalaryType::Hourly => $salary * ($workedMinutes / 60),
        };

        $hourly = match ($salaryType) {
            SalaryType::Hourly => $salary,
            SalaryType::Daily => $periodSchedule['days'] > 0 && $periodSchedule['minutes'] > 0
                ? $salary / (($periodSchedule['minutes'] / $periodSchedule['days']) / 60) : 0.0,
            SalaryType::Monthly => $periodSchedule['minutes'] > 0 ? $salary / ($periodSchedule['minutes'] / 60) : 0.0,
        };

        $overtimeAmount = 0.0;
        if ($settings['overtime_pay_enabled'] && $overtimeMinutes > 0) {
            $multiplier = (float) $settings['overtime_rate_multiplier'];
            $factor = $salaryType === SalaryType::Hourly ? max(0, $multiplier - 1) : $multiplier;
            $overtimeAmount = $hourly * ($overtimeMinutes / 60) * $factor;
        }

        $penalties = (float) Penalty::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', PenaltyStatus::Approved->value)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->sum('amount');

        $base = round($base, 2);
        $overtimeAmount = round($overtimeAmount, 2);
        $gross = round($base + $overtimeAmount, 2);
        $net = round(max(0, $gross + $bonus - $penalties), 2);

        return [
            'salary_type' => $salaryType,
            'rate' => $salary,
            'base_salary' => $base,
            'scheduled_days' => $periodSchedule['days'],
            'scheduled_minutes' => $periodSchedule['minutes'],
            'worked_days' => $workedDays,
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyMinutes,
            'absence_days' => $absenceDays,
            'overtime_amount' => $overtimeAmount,
            'gross_amount' => $gross,
            'penalty_amount' => round($penalties, 2),
            'bonus_amount' => round($bonus, 2),
            'net_amount' => $net,
            'currency' => $organization->currency,
            'details' => [
                'monthly_mode' => $settings['monthly_mode'],
                'employed_scheduled_days' => $employedSchedule['days'],
                'hourly_equivalent' => round($hourly, 4),
                'overtime_pay_enabled' => (bool) $settings['overtime_pay_enabled'],
                'overtime_multiplier' => (float) $settings['overtime_rate_multiplier'],
                'count_incomplete_as_worked' => $countIncomplete,
            ],
        ];
    }
}
