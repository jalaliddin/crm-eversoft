<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Payroll\PayrollPeriod;
use App\Services\Hr\WorkingTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Tabular HR reports; the same rows feed the UI, CSV, Excel and PDF exports. */
class AttendanceReportService
{
    public function __construct(
        private readonly AttendanceMonitorService $monitor,
        private readonly WorkingTimeService $workingTime,
    ) {}

    /** @return array{columns: array<string, string>, rows: list<array<string, mixed>>} */
    public function daily(string $date, array $filters): array
    {
        $rows = [];
        $page = 1;
        do {
            $paginator = $this->monitor->rows($date, $filters, 500, $page);
            foreach ($paginator->items() as $row) {
                $rows[] = [
                    'employee' => $row['employee']['full_name'],
                    'employee_number' => $row['employee']['employee_number'],
                    'department' => $row['department'] ?? '',
                    'check_in' => $row['check_in'],
                    'check_out' => $row['check_out'],
                    'worked_minutes' => $row['worked_minutes'],
                    'late_minutes' => $row['late_minutes'],
                    'early_leave_minutes' => $row['early_leave_minutes'],
                    'status' => $row['status'] ?? $row['live_status'],
                ];
            }
            $page++;
        } while ($page <= $paginator->lastPage());

        return [
            'columns' => [
                'employee' => 'Employee', 'employee_number' => 'No.', 'department' => 'Department', 'check_in' => 'Check-in',
                'check_out' => 'Check-out', 'worked_minutes' => 'Worked (min)', 'late_minutes' => 'Late (min)',
                'early_leave_minutes' => 'Early leave (min)', 'status' => 'Status',
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{columns: array<string, string>, rows: list<array<string, mixed>>} */
    public function monthly(int $year, int $month, array $filters): array
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth()->startOfDay();
        $rows = [];

        Employee::query()
            ->with(['department:id,name', 'organization'])
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $end->format('Y-m-d')))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $start->format('Y-m-d')))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->chunkById(500, function (Collection $employees) use ($start, $end, &$rows): void {
                $this->workingTime->resolver()->preload($employees);
                $stats = AttendanceRecord::query()
                    ->whereIn('employee_id', $employees->pluck('id'))
                    ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->selectRaw("employee_id,
                        sum(status in ('present','late','early_leave','incomplete')) present_days,
                        sum(status = 'absent') absent_days,
                        sum(late_minutes > 0) late_count,
                        sum(late_minutes) late_minutes,
                        sum(early_leave_minutes > 0) early_count,
                        sum(early_leave_minutes) early_minutes,
                        sum(worked_minutes) worked_minutes,
                        sum(overtime_minutes) overtime_minutes")
                    ->groupBy('employee_id')
                    ->get()
                    ->keyBy('employee_id');

                foreach ($employees as $employee) {
                    $s = $stats->get($employee->id);
                    $scheduled = $this->workingTime->scheduled($employee, $start, $end);
                    $rows[] = [
                        'employee' => $employee->full_name,
                        'employee_number' => $employee->employee_number,
                        'department' => $employee->department?->name ?? '',
                        'work_days' => $scheduled['days'],
                        'present_days' => (int) ($s->present_days ?? 0),
                        'absent_days' => (int) ($s->absent_days ?? 0),
                        'late_count' => (int) ($s->late_count ?? 0),
                        'late_minutes' => (int) ($s->late_minutes ?? 0),
                        'early_leave_count' => (int) ($s->early_count ?? 0),
                        'worked_hours' => round(((int) ($s->worked_minutes ?? 0)) / 60, 2),
                        'overtime_hours' => round(((int) ($s->overtime_minutes ?? 0)) / 60, 2),
                    ];
                }
            });

        usort($rows, fn ($a, $b) => strcmp($a['employee'], $b['employee']));

        return [
            'columns' => [
                'employee' => 'Employee', 'employee_number' => 'No.', 'department' => 'Department', 'work_days' => 'Work days',
                'present_days' => 'Present days', 'absent_days' => 'Absent days', 'late_count' => 'Late count',
                'late_minutes' => 'Late minutes', 'early_leave_count' => 'Early leave count', 'worked_hours' => 'Worked hours',
                'overtime_hours' => 'Overtime hours',
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{columns: array<string, string>, rows: list<array<string, mixed>>} */
    public function payroll(PayrollPeriod $period): array
    {
        $rows = $period->records()->with(['employee' => fn ($q) => $q->withTrashed()->with('department:id,name')])
            ->get()
            ->sortBy(fn ($r) => $r->employee?->full_name)
            ->map(fn ($r) => [
                'employee' => $r->employee?->full_name,
                'employee_number' => $r->employee?->employee_number,
                'department' => $r->employee?->department?->name ?? '',
                'salary_type' => $r->salary_type->value,
                'base' => (float) $r->base_salary,
                'worked_days' => $r->worked_days,
                'worked_hours' => round($r->worked_minutes / 60, 2),
                'overtime' => (float) $r->overtime_amount,
                'penalties' => (float) $r->penalty_amount,
                'bonuses' => (float) $r->bonus_amount,
                'net' => (float) $r->net_amount,
                'status' => $r->status->value,
            ])->values()->all();

        return [
            'columns' => [
                'employee' => 'Employee', 'employee_number' => 'No.', 'department' => 'Department', 'salary_type' => 'Salary type',
                'base' => 'Base', 'worked_days' => 'Worked days', 'worked_hours' => 'Worked hours', 'overtime' => 'Overtime',
                'penalties' => 'Penalties', 'bonuses' => 'Bonuses', 'net' => 'Net', 'status' => 'Status',
            ],
            'rows' => $rows,
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return $status ? (AttendanceStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status))) : '';
    }
}
