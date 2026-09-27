<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\RecordStatus;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkSchedule;
use App\Models\Hr\Holiday;
use App\Models\Hr\WorkSchedule;
use App\Models\Organization;
use App\Services\Attendance\Data\DaySchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves which schedule applies to an employee on a business date, and to which business date a punch belongs.
 * Memoizes lookups for the lifetime of the instance — create a fresh one per request/job.
 */
class ScheduleResolver
{
    /** Punches up to this many hours after an overnight shift ends belong to the previous business date. */
    private const OVERNIGHT_TAIL_HOURS = 6;

    /** @var array<int, WorkSchedule|null> */
    private array $schedules = [];

    /** @var array<int, Collection<int, EmployeeWorkSchedule>> */
    private array $assignments = [];

    /** @var array<int, WorkSchedule|null> organization id => default schedule */
    private array $defaults = [];

    /** @var array<string, array<string, string>> "orgId:year" => [date => name] */
    private array $holidays = [];

    /** @var array<int, Organization> */
    private array $organizations = [];

    public function scheduleFor(Employee $employee, CarbonInterface $date): ?WorkSchedule
    {
        $day = $date->format('Y-m-d');

        $assignment = $this->assignmentsFor($employee)->first(
            fn (EmployeeWorkSchedule $a) => $a->effective_from->format('Y-m-d') <= $day
                && ($a->effective_to === null || $a->effective_to->format('Y-m-d') >= $day),
        );

        if ($assignment !== null) {
            return $this->schedule($assignment->work_schedule_id);
        }

        return $this->defaultSchedule($employee->organization_id);
    }

    public function daySchedule(Employee $employee, CarbonInterface|string $date): DaySchedule
    {
        $organization = $this->organization($employee->organization_id);
        $schedule = $this->scheduleFor($employee, is_string($date) ? CarbonImmutable::parse($date) : $date);
        $timezone = $schedule?->timezone ?: $organization->timezone;
        $localDate = CarbonImmutable::parse(is_string($date) ? $date : $date->format('Y-m-d'), $timezone)->startOfDay();
        $holiday = $this->holidayName($employee->organization_id, $localDate);

        if ($schedule === null) {
            $workWeek = (array) $organization->setting('work_week', [1, 2, 3, 4, 5]);

            return DaySchedule::unscheduled($localDate, $timezone, in_array($localDate->dayOfWeekIso, $workWeek, true), $holiday);
        }

        return DaySchedule::fromSchedule($schedule, $localDate, $timezone, $holiday);
    }

    /** Business date a punch belongs to (handles overnight shifts). */
    public function businessDateFor(Employee $employee, CarbonInterface $instant): CarbonImmutable
    {
        $organization = $this->organization($employee->organization_id);
        $timezone = $this->scheduleFor($employee, CarbonImmutable::instance($instant)->setTimezone($organization->timezone))?->timezone
            ?: $organization->timezone;
        $local = CarbonImmutable::instance($instant)->setTimezone($timezone);
        $previous = $local->subDay()->startOfDay();

        $previousDay = $this->daySchedule($employee, $previous->format('Y-m-d'));
        if ($previousDay->crossesMidnight() && $previousDay->end !== null
            && $local->lessThanOrEqualTo($previousDay->end->addHours(self::OVERNIGHT_TAIL_HOURS))) {
            return $previous;
        }

        return $local->startOfDay();
    }

    public function organization(int $organizationId): Organization
    {
        return $this->organizations[$organizationId] ??= Organization::query()->findOrFail($organizationId);
    }

    /** Preload assignments for many employees at once (reports, monitor, close-day). */
    public function preload(Collection $employees): void
    {
        $ids = $employees->pluck('id')->diff(array_keys($this->assignments))->values();
        if ($ids->isEmpty()) {
            return;
        }

        $rows = EmployeeWorkSchedule::query()->whereIn('employee_id', $ids)->orderByDesc('effective_from')->get()->groupBy('employee_id');
        foreach ($ids as $id) {
            $this->assignments[$id] = $rows->get($id, collect());
        }
    }

    /** @return Collection<int, EmployeeWorkSchedule> */
    private function assignmentsFor(Employee $employee): Collection
    {
        return $this->assignments[$employee->id] ??= EmployeeWorkSchedule::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get();
    }

    private function schedule(int $id): ?WorkSchedule
    {
        if (! array_key_exists($id, $this->schedules)) {
            $this->schedules[$id] = WorkSchedule::withoutGlobalScopes()->with('days')->find($id);
        }

        return $this->schedules[$id];
    }

    private function defaultSchedule(int $organizationId): ?WorkSchedule
    {
        if (! array_key_exists($organizationId, $this->defaults)) {
            $this->defaults[$organizationId] = WorkSchedule::withoutGlobalScopes()
                ->with('days')
                ->where('organization_id', $organizationId)
                ->where('is_default', true)
                ->where('status', RecordStatus::Active->value)
                ->first();
        }

        return $this->defaults[$organizationId];
    }

    private function holidayName(int $organizationId, CarbonImmutable $date): ?string
    {
        $key = $organizationId.':'.$date->year;

        $this->holidays[$key] ??= Holiday::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereYear('date', $date->year)
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->format('Y-m-d') => $h->name])
            ->all();

        return $this->holidays[$key][$date->format('Y-m-d')] ?? null;
    }
}
