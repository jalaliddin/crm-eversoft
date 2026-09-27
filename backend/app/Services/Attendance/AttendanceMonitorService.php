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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Read model for the attendance monitor, HR dashboard and daily report.
 * Stateless per call so it can later back a WebSocket push without changes.
 *
 * Live statuses (today): working (checked in, not out), left, not_arrived (before start + grace),
 * absent (after start + grace, no check-in), weekend, holiday, on_leave.
 */
class AttendanceMonitorService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ScheduleResolver $resolver,
    ) {}

    /** @return array<string, int> */
    public function summary(string $date): array
    {
        $organization = $this->tenant->require();
        $ttl = $date === CarbonImmutable::now($organization->timezone)->format('Y-m-d') ? 20 : 300;

        return Cache::remember("attendance-summary:{$organization->id}:{$date}", $ttl, function () use ($date) {
            $records = AttendanceRecord::query()->where('date', $date)->selectRaw("
                sum(actual_check_in is not null) as present,
                sum(late_minutes > 0) as late,
                sum(actual_check_in is not null and actual_check_out is null and status <> 'incomplete') as working,
                sum(actual_check_out is not null) as `left`,
                sum(status = 'incomplete') as incomplete,
                sum(status = 'on_leave') as on_leave,
                sum(status = 'absent') as absent_records,
                sum(early_leave_minutes > 0) as early_leave
            ")->first();

            $present = (int) $records->present;
            $onLeave = (int) $records->on_leave;
            $total = 0;
            $expectedNow = 0;
            $now = CarbonImmutable::now();

            $this->trackedOn($date)->with('organization')->select('employees.*')->chunkById(1000, function ($employees) use ($date, $now, &$total, &$expectedNow): void {
                $this->resolver->preload($employees);
                foreach ($employees as $employee) {
                    $total++;
                    $day = $this->resolver->daySchedule($employee, $date);
                    if ($employee->status === EmployeeStatus::OnLeave || ! $day->expectsWork()) {
                        continue;
                    }
                    $deadline = $day->start?->addMinutes($day->graceMinutes) ?? $day->date->endOfDay();
                    if ($now->greaterThan($deadline)) {
                        $expectedNow++;
                    }
                }
            });

            // Before the day is closed, absent = expected by now − present (those with a record already counted).
            $absent = max((int) $records->absent_records, $expectedNow - $present - $onLeave);

            return [
                'total_employees' => $total,
                'present' => $present,
                'late' => (int) $records->late,
                'absent' => max(0, $absent),
                'working' => (int) $records->working,
                'left' => (int) $records->left,
                'incomplete' => (int) $records->incomplete,
                'on_leave' => $onLeave,
                'early_leave' => (int) $records->early_leave,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters  department_id, employee_id, search, status, device_id, source
     */
    public function rows(string $date, array $filters, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $query = $this->trackedOn($date)
            ->with(['department:id,name', 'position:id,name', 'organization'])
            ->leftJoin('attendance_records as ar', fn ($join) => $join->on('ar.employee_id', '=', 'employees.id')->where('ar.date', '=', $date))
            ->select('employees.*', 'ar.id as record_id')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('employees.department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employees.id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('employees.first_name', 'like', "%{$v}%")
                ->orWhere('employees.last_name', 'like', "%{$v}%")->orWhere('employees.employee_number', 'like', "%{$v}%")))
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('ar.device_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('ar.source', $v));

        match ($filters['status'] ?? null) {
            'present' => $query->whereNotNull('ar.actual_check_in'),
            'late' => $query->where('ar.late_minutes', '>', 0),
            'early_leave' => $query->where('ar.early_leave_minutes', '>', 0),
            'working' => $query->whereNotNull('ar.actual_check_in')->whereNull('ar.actual_check_out')->where('ar.status', '!=', 'incomplete'),
            'left' => $query->whereNotNull('ar.actual_check_out'),
            'incomplete' => $query->where('ar.status', 'incomplete'),
            'on_leave' => $query->where(fn ($q) => $q->where('ar.status', 'on_leave')->orWhere(fn ($q) => $q->whereNull('ar.id')->where('employees.status', 'on_leave'))),
            'absent' => $query->where(fn ($q) => $q->where('ar.status', 'absent')->orWhereNull('ar.id')),
            default => null,
        };

        $paginator = $query->orderBy('employees.last_name')->orderBy('employees.first_name')->paginate($perPage, ['*'], 'page', $page);

        $records = AttendanceRecord::query()
            ->with('device:id,name')
            ->whereIn('id', $paginator->getCollection()->pluck('record_id')->filter())
            ->get()
            ->keyBy('employee_id');

        $this->resolver->preload($paginator->getCollection());
        $paginator->setCollection($paginator->getCollection()->map(
            fn (Employee $employee) => $this->row($employee, $records->get($employee->id), $date),
        ));

        return $paginator;
    }

    /** @return array<string, mixed> */
    public function row(Employee $employee, ?AttendanceRecord $record, string $date): array
    {
        $day = $this->resolver->daySchedule($employee, $date);
        $schedule = $day->scheduleId ? $this->resolver->scheduleFor($employee, CarbonImmutable::parse($date)) : null;

        return [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'photo_url' => $employee->photoUrl(),
            ],
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'schedule' => [
                'name' => $schedule?->name,
                'start' => $day->start?->format('H:i'),
                'end' => $day->end?->format('H:i'),
                'is_working_day' => $day->isWorkingDay,
                'holiday' => $day->holidayName,
            ],
            'record_id' => $record?->id,
            'check_in' => $record?->actual_check_in?->toIso8601ZuluString(),
            'check_out' => $record?->actual_check_out?->toIso8601ZuluString(),
            'worked_minutes' => $record?->worked_minutes ?? 0,
            'late_minutes' => $record?->late_minutes ?? 0,
            'early_leave_minutes' => $record?->early_leave_minutes ?? 0,
            'overtime_minutes' => $record?->overtime_minutes ?? 0,
            'status' => $record?->status?->value,
            'live_status' => $this->liveStatus($employee, $record, $day),
            'source' => $record?->source?->value,
            'device' => $record?->device?->name,
            'is_manual' => (bool) $record?->is_manual,
        ];
    }

    /** @return list<array{date: string, present: int, late: int, absent: int}> */
    public function trend(string $from, string $to): array
    {
        $to = CarbonImmutable::parse($to);
        $from = CarbonImmutable::parse($from)->max($to->subDays(89));

        $rows = AttendanceRecord::query()
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->selectRaw("date, sum(actual_check_in is not null) present, sum(late_minutes > 0) late, sum(status = 'absent') absent, sum(early_leave_minutes > 0) early_leave")
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->date)->format('Y-m-d'));

        $points = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->format('Y-m-d');
            $points[] = [
                'date' => $key,
                'present' => (int) ($rows[$key]->present ?? 0),
                'late' => (int) ($rows[$key]->late ?? 0),
                'absent' => (int) ($rows[$key]->absent ?? 0),
                'early_leave' => (int) ($rows[$key]->early_leave ?? 0),
            ];
        }

        return $points;
    }

    /** @return list<array{department: string, present: int, late: int, absent: int}> */
    public function byDepartment(string $date): array
    {
        return AttendanceRecord::query()
            ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('attendance_records.date', $date)
            ->selectRaw("coalesce(departments.name, '—') department,
                sum(attendance_records.actual_check_in is not null) present,
                sum(attendance_records.late_minutes > 0) late,
                sum(attendance_records.status = 'absent') absent")
            ->groupBy('departments.name')
            ->orderBy('department')
            ->get()
            ->map(fn ($r) => ['department' => $r->department, 'present' => (int) $r->present, 'late' => (int) $r->late, 'absent' => (int) $r->absent])
            ->all();
    }

    /** Employees tracked on a date (hired, not terminated). */
    public function trackedOn(string $date): Builder
    {
        return Employee::query()
            ->tracked()
            ->where(fn ($q) => $q->whereNull('employees.hire_date')->orWhere('employees.hire_date', '<=', $date))
            ->where(fn ($q) => $q->whereNull('employees.termination_date')->orWhere('employees.termination_date', '>=', $date));
    }

    private function liveStatus(Employee $employee, ?AttendanceRecord $record, Data\DaySchedule $day): string
    {
        if ($record?->actual_check_in !== null) {
            if ($record->actual_check_out !== null) {
                return 'left';
            }

            return $record->status === AttendanceStatus::Incomplete ? 'incomplete' : 'working';
        }

        if ($record !== null) {
            return $record->status->value;
        }

        if ($employee->status === EmployeeStatus::OnLeave) {
            return 'on_leave';
        }
        if ($day->isHoliday()) {
            return 'holiday';
        }
        if (! $day->isWorkingDay) {
            return 'weekend';
        }

        $deadline = $day->start?->addMinutes($day->graceMinutes) ?? $day->date->endOfDay();

        return CarbonImmutable::now()->greaterThan($deadline) ? 'absent' : 'not_arrived';
    }
}
