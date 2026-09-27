<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Services\Hr\ScheduleResolver;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Monthly calendar. Weekends and holidays are derived from schedules (not stored per employee),
 * so the calendar merges stored records with virtual W/H days.
 *
 * Codes: P present · L late · A absent · EL early leave · I incomplete · OL on leave · H holiday · W weekend
 */
class AttendanceCalendarService
{
    public const CODES = [
        'present' => 'P',
        'late' => 'L',
        'absent' => 'A',
        'early_leave' => 'EL',
        'incomplete' => 'I',
        'on_leave' => 'OL',
        'holiday' => 'H',
        'weekend' => 'W',
    ];

    public function __construct(
        private readonly ScheduleResolver $resolver,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forEmployee(Employee $employee, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth()->startOfDay();
        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(fn (AttendanceRecord $r) => $r->date->format('Y-m-d'));

        return $this->days($employee, $start, $end, $records);
    }

    /** Employees × days matrix for the organization-wide calendar. */
    public function matrix(int $year, int $month, array $filters, int $perPage): LengthAwarePaginator
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth()->startOfDay();

        $paginator = Employee::query()
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $end->format('Y-m-d')))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $start->format('Y-m-d')))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->search($v))
            ->with(['department:id,name', 'organization'])
            ->orderBy('last_name')
            ->paginate($perPage);

        $this->resolver->preload($paginator->getCollection());
        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $paginator->getCollection()->pluck('id'))
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->groupBy('employee_id');

        $paginator->setCollection($paginator->getCollection()->map(fn (Employee $employee) => [
            'employee' => ['id' => $employee->id, 'full_name' => $employee->full_name, 'employee_number' => $employee->employee_number, 'department' => $employee->department?->name],
            'days' => array_map(
                fn ($d) => ['date' => $d['date'], 'code' => $d['code'], 'status' => $d['status']],
                $this->days($employee, $start, $end, ($records->get($employee->id) ?? collect())->keyBy(fn ($r) => $r->date->format('Y-m-d'))),
            ),
        ]));

        return $paginator;
    }

    /**
     * @param  Collection<string, AttendanceRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function days(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, Collection $records): array
    {
        $today = CarbonImmutable::now($this->tenant->timezone())->format('Y-m-d');
        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->format('Y-m-d');
            /** @var AttendanceRecord|null $record */
            $record = $records->get($key);
            $employed = (! $employee->hire_date || $employee->hire_date->format('Y-m-d') <= $key)
                && (! $employee->termination_date || $employee->termination_date->format('Y-m-d') >= $key);

            if ($record !== null) {
                $status = $record->status->value;
            } elseif (! $employed || $key > $today) {
                $day = $this->resolver->daySchedule($employee, $key);
                $status = $day->isHoliday() ? 'holiday' : (! $day->isWorkingDay ? 'weekend' : null);
            } else {
                $day = $this->resolver->daySchedule($employee, $key);
                $status = match (true) {
                    $day->isHoliday() => 'holiday',
                    ! $day->isWorkingDay => 'weekend',
                    $employee->status === EmployeeStatus::OnLeave => 'on_leave',
                    // Past working day without a record and not yet closed → treat as absent.
                    $key < $today => AttendanceStatus::Absent->value,
                    default => null,
                };
            }

            $days[] = [
                'date' => $key,
                'status' => $status,
                'code' => $status ? self::CODES[$status] : null,
                'record_id' => $record?->id,
                'check_in' => $record?->actual_check_in?->toIso8601ZuluString(),
                'check_out' => $record?->actual_check_out?->toIso8601ZuluString(),
                'worked_minutes' => $record?->worked_minutes ?? 0,
                'late_minutes' => $record?->late_minutes ?? 0,
                'early_leave_minutes' => $record?->early_leave_minutes ?? 0,
                'overtime_minutes' => $record?->overtime_minutes ?? 0,
            ];
        }

        return $days;
    }
}
