<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\RawEventStatus;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Services\Attendance\Data\DaySchedule;
use App\Services\Hr\ScheduleResolver;
use App\Services\Payroll\PenaltyService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns processed raw punches into attendance_records and closes business days.
 *
 * Idempotent: a record is always recomputed from the full set of punches for its business date,
 * so re-running a sync or re-processing events converges to the same result.
 * Manually corrected records keep their check-in/out; only their metrics are recalculated.
 */
class AttendanceProcessor
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly ScheduleResolver $resolver,
        private readonly PenaltyService $penalties,
        private readonly AttendanceNotifier $notifier,
    ) {}

    public function resolver(): ScheduleResolver
    {
        return $this->resolver;
    }

    public function recompute(Employee $employee, CarbonImmutable $date, ?CarbonImmutable $now = null): ?AttendanceRecord
    {
        $now ??= CarbonImmutable::now();
        $day = $this->resolver->daySchedule($employee, $date->format('Y-m-d'));
        $punches = $this->punchesFor($employee, $day);

        return DB::transaction(function () use ($employee, $day, $punches, $now) {
            /** @var AttendanceRecord|null $record */
            $record = AttendanceRecord::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('date', $day->date->format('Y-m-d'))
                ->lockForUpdate()
                ->first();

            if ($record?->edited_at !== null) {
                return $this->recalculate($record, $now);
            }

            if ($punches->isEmpty()) {
                return $record === null ? null : $this->recalculate($record, $now);
            }

            $window = (int) $this->resolver->organization($employee->organization_id)->setting('attendance.duplicate_punch_window_seconds', 60);
            $first = $punches->first();
            $last = $punches->last();
            $checkIn = CarbonImmutable::instance($first->event_time);
            $checkOut = $last->id !== $first->id && $last->event_time->getTimestamp() - $first->event_time->getTimestamp() > $window
                ? CarbonImmutable::instance($last->event_time)
                : null;

            $metrics = $this->calculator->calculate($day, $checkIn, $checkOut, $this->isDayClosed($day, $now));
            $previous = $record?->status;

            $record ??= new AttendanceRecord([
                'employee_id' => $employee->id,
                'date' => $day->date->format('Y-m-d'),
            ]);
            $record->organization_id = $employee->organization_id;
            $record->fill([
                'work_schedule_id' => $day->scheduleId,
                'scheduled_start' => $day->start?->utc(),
                'scheduled_end' => $day->end?->utc(),
                'actual_check_in' => $checkIn->utc(),
                'actual_check_out' => $checkOut?->utc(),
                'source' => AttendanceSource::Biometric,
                'device_id' => $first->device_id,
                'raw_event_id' => $first->id,
                ...$metrics->toArray(),
            ]);
            $record->save();
            $record->setRelation('employee', $employee);

            $this->penalties->syncForRecord($record);
            $this->notifier->statusChanged($record, $previous);

            return $record;
        });
    }

    /** Recalculate metrics of an existing record from its stored check-in/out (schedule change, manual edit, day close). */
    public function recalculate(AttendanceRecord $record, ?CarbonImmutable $now = null, ?AttendanceStatus $forcedStatus = null, ?bool $dayClosed = null): AttendanceRecord
    {
        $now ??= CarbonImmutable::now();
        $employee = $record->employee;
        $day = $this->resolver->daySchedule($employee, $record->date->format('Y-m-d'));
        $previous = $record->getOriginal('status');

        if ($forcedStatus !== null && $record->actual_check_in === null) {
            $record->fill(['status' => $forcedStatus, 'worked_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0]);
        } else {
            $metrics = $this->calculator->calculate(
                $day,
                $record->actual_check_in,
                $record->actual_check_out,
                $dayClosed ?? $this->isDayClosed($day, $now),
                $employee->status === EmployeeStatus::OnLeave,
            );
            $record->fill($metrics->toArray());
            if ($forcedStatus !== null) {
                $record->status = $forcedStatus;
            }
        }

        $record->fill([
            'work_schedule_id' => $day->scheduleId,
            'scheduled_start' => $day->start?->utc(),
            'scheduled_end' => $day->end?->utc(),
        ]);
        $record->save();

        $this->penalties->syncForRecord($record);
        $this->notifier->statusChanged($record, $previous instanceof AttendanceStatus ? $previous : null);

        return $record;
    }

    /**
     * Close a business day: open check-ins become incomplete, expected employees without
     * a record become absent (or on_leave). Weekends/holidays are not materialized.
     *
     * @return array{absent: int, incomplete: int, on_leave: int}
     */
    public function closeDay(Organization $organization, CarbonImmutable $date): array
    {
        return app(TenantContext::class)->run($organization, function () use ($organization, $date) {
            $stats = ['absent' => 0, 'incomplete' => 0, 'on_leave' => 0];
            $absentNames = [];
            $autoAbsent = (bool) $organization->setting('attendance.auto_mark_absent', true);
            $now = CarbonImmutable::now();
            $day = $date->format('Y-m-d');

            Employee::query()
                ->tracked()
                ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $day))
                ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>=', $day))
                ->with('organization')
                ->chunkById(500, function (Collection $employees) use ($day, $now, $autoAbsent, &$stats, &$absentNames): void {
                    $this->resolver->preload($employees);
                    $records = AttendanceRecord::query()
                        ->whereIn('employee_id', $employees->pluck('id'))
                        ->where('date', $day)
                        ->get()
                        ->keyBy('employee_id');

                    foreach ($employees as $employee) {
                        /** @var AttendanceRecord|null $record */
                        $record = $records->get($employee->id);

                        if ($record !== null) {
                            if ($record->actual_check_in !== null && $record->actual_check_out === null
                                && $record->status !== AttendanceStatus::Incomplete) {
                                $record->setRelation('employee', $employee);
                                // Closing a day is authoritative: open check-ins become incomplete.
                                $this->recalculate($record, $now, dayClosed: true);
                                $stats['incomplete'] += $record->status === AttendanceStatus::Incomplete ? 1 : 0;
                            }

                            continue;
                        }

                        $schedule = $this->resolver->daySchedule($employee, $day);
                        $status = $this->calculator->statusWithoutPresence($schedule, $employee->status === EmployeeStatus::OnLeave);

                        if ($status === AttendanceStatus::OnLeave || ($status === AttendanceStatus::Absent && $autoAbsent)) {
                            $record = $this->createWithoutPresence($employee, $schedule, $status);
                            $stats[$status === AttendanceStatus::Absent ? 'absent' : 'on_leave']++;
                            if ($status === AttendanceStatus::Absent) {
                                $absentNames[] = $employee->full_name;
                                $this->penalties->syncForRecord($record);
                            }
                        }
                    }
                });

            $this->notifier->absenceDigest($organization, $day, $stats['absent'], $absentNames);
            Log::channel('attendance')->info('Day closed', ['organization_id' => $organization->id, 'date' => $day, ...$stats]);

            return $stats;
        });
    }

    /** A business day is closed once its scheduled end (or local midnight) plus a buffer has passed. */
    public function isDayClosed(DaySchedule $day, CarbonImmutable $now): bool
    {
        $end = $day->end ?? $day->date->addDay();

        return $now->greaterThan($end->max($day->date->addDay())->addHours(2));
    }

    /** @return Collection<int, AttendanceRawEvent> */
    private function punchesFor(Employee $employee, DaySchedule $day): Collection
    {
        $from = $day->date->subHours(12)->utc();
        $to = $day->date->addDay()->addHours(18)->utc();
        $target = $day->date->format('Y-m-d');

        return AttendanceRawEvent::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('processing_status', RawEventStatus::Processed->value)
            ->whereBetween('event_time', [$from, $to])
            ->orderBy('event_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (AttendanceRawEvent $e) => $this->resolver->businessDateFor($employee, $e->event_time)->format('Y-m-d') === $target)
            ->values();
    }

    private function createWithoutPresence(Employee $employee, DaySchedule $day, AttendanceStatus $status): AttendanceRecord
    {
        $record = AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => $day->date->format('Y-m-d'),
            'work_schedule_id' => $day->scheduleId,
            'scheduled_start' => $day->start?->utc(),
            'scheduled_end' => $day->end?->utc(),
            'status' => $status,
            'source' => AttendanceSource::System,
        ]);
        $record->setRelation('employee', $employee);

        return $record;
    }
}
