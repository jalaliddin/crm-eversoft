<?php

declare(strict_types=1);

namespace App\Services\Attendance\Data;

use App\Enums\LateMode;
use App\Enums\ScheduleType;
use App\Models\Hr\WorkSchedule;
use Carbon\CarbonImmutable;

/**
 * A work schedule resolved to concrete instants for one business date.
 * All instants are in the schedule timezone; convert with ->utc() before persisting.
 */
final readonly class DaySchedule
{
    public function __construct(
        public CarbonImmutable $date,
        public string $timezone,
        public bool $isWorkingDay,
        public ?string $holidayName = null,
        public ?CarbonImmutable $start = null,
        public ?CarbonImmutable $end = null,
        public ?CarbonImmutable $breakStart = null,
        public ?CarbonImmutable $breakEnd = null,
        public int $breakMinutes = 0,
        public int $graceMinutes = 0,
        public int $earlyLeaveGraceMinutes = 0,
        public LateMode $lateMode = LateMode::AfterGrace,
        public ?int $requiredMinutes = null,
        public bool $overtimeEnabled = false,
        public int $overtimeThresholdMinutes = 0,
        public bool $flexible = false,
        public ?int $scheduleId = null,
    ) {}

    public static function fromSchedule(WorkSchedule $schedule, CarbonImmutable $date, string $timezone, ?string $holidayName = null): self
    {
        $day = $schedule->dayFor($date->dayOfWeekIso);
        $flexible = $schedule->type === ScheduleType::Flexible;
        $working = $day !== null && $day->is_working_day;

        $start = $end = $breakStart = $breakEnd = null;

        if ($working && $day->start_time && $day->end_time && ! $flexible) {
            $start = self::at($date, $day->start_time, $timezone);
            $end = self::at($date, $day->end_time, $timezone);
            // Overnight shift: end on the following day.
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            if ($day->break_start && $day->break_end) {
                $breakStart = self::at($date, $day->break_start, $timezone);
                if ($breakStart->lessThan($start)) {
                    $breakStart = $breakStart->addDay();
                }
                $breakEnd = self::at($breakStart, $day->break_end, $timezone);
                if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                    $breakEnd = $breakEnd->addDay();
                }
            }
        }

        $breakMinutes = $breakStart && $breakEnd
            ? (int) $breakStart->diffInMinutes($breakEnd)
            : (int) ($day?->break_minutes ?? 0);

        $required = $schedule->required_daily_minutes;
        if ($required === null && $start && $end) {
            $required = max(0, (int) $start->diffInMinutes($end) - $breakMinutes);
        }

        return new self(
            date: $date,
            timezone: $timezone,
            isWorkingDay: $working,
            holidayName: $holidayName,
            start: $start,
            end: $end,
            breakStart: $breakStart,
            breakEnd: $breakEnd,
            breakMinutes: $breakMinutes,
            graceMinutes: $schedule->grace_minutes,
            earlyLeaveGraceMinutes: $schedule->early_leave_grace_minutes,
            lateMode: $schedule->late_mode ?? LateMode::AfterGrace,
            requiredMinutes: $working ? $required : 0,
            overtimeEnabled: $schedule->overtime_enabled,
            overtimeThresholdMinutes: $schedule->overtime_threshold_minutes,
            flexible: $flexible,
            scheduleId: $schedule->id,
        );
    }

    /** Employee without any schedule: working days follow the organization work week, no fixed hours. */
    public static function unscheduled(CarbonImmutable $date, string $timezone, bool $isWorkingDay, ?string $holidayName = null): self
    {
        return new self(date: $date, timezone: $timezone, isWorkingDay: $isWorkingDay, holidayName: $holidayName, flexible: true, requiredMinutes: 0);
    }

    public function isHoliday(): bool
    {
        return $this->holidayName !== null;
    }

    /** Whether the employee is expected to work this day. */
    public function expectsWork(): bool
    {
        return $this->isWorkingDay && ! $this->isHoliday();
    }

    public function crossesMidnight(): bool
    {
        return $this->start !== null && $this->end !== null && ! $this->start->isSameDay($this->end);
    }

    private static function at(CarbonImmutable $date, string $time, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d').' '.$time, $timezone);
    }
}
