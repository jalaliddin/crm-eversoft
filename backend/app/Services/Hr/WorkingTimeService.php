<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\SalaryType;
use App\Models\Hr\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/** Scheduled working time and pay rates derived from schedules — shared by penalties and payroll. */
class WorkingTimeService
{
    /** @var array<string, array{days: int, minutes: int}> */
    private array $cache = [];

    public function __construct(private readonly ScheduleResolver $resolver) {}

    public function resolver(): ScheduleResolver
    {
        return $this->resolver;
    }

    /**
     * Scheduled working days/minutes in [from, to], optionally limited to the employment period.
     *
     * @return array{days: int, minutes: int}
     */
    public function scheduled(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, bool $withinEmployment = true): array
    {
        if ($withinEmployment) {
            if ($employee->hire_date && $employee->hire_date->format('Y-m-d') > $from->format('Y-m-d')) {
                $from = CarbonImmutable::parse($employee->hire_date->format('Y-m-d'));
            }
            if ($employee->termination_date && $employee->termination_date->format('Y-m-d') < $to->format('Y-m-d')) {
                $to = CarbonImmutable::parse($employee->termination_date->format('Y-m-d'));
            }
        }

        $key = implode(':', [$employee->id, $from->format('Ymd'), $to->format('Ymd')]);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $days = 0;
        $minutes = 0;

        if ($from->lessThanOrEqualTo($to)) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $day = $this->resolver->daySchedule($employee, $date->format('Y-m-d'));
                if ($day->expectsWork()) {
                    $days++;
                    $minutes += $day->requiredMinutes ?? 0;
                }
            }
        }

        return $this->cache[$key] = ['days' => $days, 'minutes' => $minutes];
    }

    /** @return array{days: int, minutes: int} */
    public function scheduledInMonth(Employee $employee, CarbonImmutable $date, bool $withinEmployment = false): array
    {
        return $this->scheduled($employee, $date->startOfMonth(), $date->endOfMonth()->startOfDay(), $withinEmployment);
    }

    /** Pay for one scheduled working day. */
    public function dailyRate(Employee $employee, CarbonImmutable $date): float
    {
        $salary = (float) $employee->salary_amount;

        return match ($employee->salary_type) {
            SalaryType::Daily => $salary,
            SalaryType::Hourly => $salary * (($this->resolver->daySchedule($employee, $date->format('Y-m-d'))->requiredMinutes ?: 480) / 60),
            default => ($days = $this->scheduledInMonth($employee, $date)['days']) > 0 ? $salary / $days : 0.0,
        };
    }

    /** Pay for one hour of scheduled work. */
    public function hourlyRate(Employee $employee, CarbonImmutable $date): float
    {
        $salary = (float) $employee->salary_amount;

        return match ($employee->salary_type) {
            SalaryType::Hourly => $salary,
            SalaryType::Daily => $salary / (($this->resolver->daySchedule($employee, $date->format('Y-m-d'))->requiredMinutes ?: 480) / 60),
            default => ($minutes = $this->scheduledInMonth($employee, $date)['minutes']) > 0 ? $salary / ($minutes / 60) : 0.0,
        };
    }
}
