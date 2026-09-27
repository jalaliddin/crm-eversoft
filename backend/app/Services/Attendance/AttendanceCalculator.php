<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\LateMode;
use App\Services\Attendance\Data\AttendanceMetrics;
use App\Services\Attendance\Data\DaySchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pure attendance math. No database access, no clock access — fully deterministic and unit-testable.
 *
 * Rules
 *  - Late (after_grace):  late = check_in − (start + grace) when check_in is after start + grace.
 *  - Late (from_start):   once check_in is after start + grace, late = check_in − start.
 *  - Early leave:         early = end − check_out when check_out is before end − early_leave_grace.
 *  - Worked:              (check_out − check_in) minus the part overlapping the break window
 *                         (or minus break_minutes when the break has no fixed window).
 *  - Overtime:            worked − required, when overtime is enabled and ≥ threshold.
 *                         Work on a weekend/holiday counts entirely as overtime.
 *  - Minutes are truncated (08:45:59 vs 08:40 → 5 minutes).
 */
final class AttendanceCalculator
{
    /**
     * @param  bool  $dayClosed  true once the business day is over; an open check-in then becomes "incomplete".
     */
    public function calculate(
        DaySchedule $schedule,
        ?CarbonInterface $checkIn,
        ?CarbonInterface $checkOut,
        bool $dayClosed = true,
        bool $onLeave = false,
    ): AttendanceMetrics {
        if ($checkIn === null) {
            return new AttendanceMetrics($this->statusWithoutPresence($schedule, $onLeave));
        }

        $checkIn = CarbonImmutable::instance($checkIn);
        $checkOut = $checkOut !== null ? CarbonImmutable::instance($checkOut) : null;

        if ($checkOut !== null && $checkOut->lessThanOrEqualTo($checkIn)) {
            $checkOut = null;
        }

        $worked = $checkOut !== null ? $this->workedMinutes($schedule, $checkIn, $checkOut) : 0;

        // Work on a day off.
        if (! $schedule->expectsWork()) {
            $overtime = $schedule->overtimeEnabled && $worked >= $schedule->overtimeThresholdMinutes ? $worked : 0;
            $status = $checkOut === null && $dayClosed ? AttendanceStatus::Incomplete : AttendanceStatus::Present;

            return new AttendanceMetrics($status, $worked, 0, 0, $overtime);
        }

        $late = $this->lateMinutes($schedule, $checkIn);
        $early = $checkOut !== null ? $this->earlyLeaveMinutes($schedule, $checkOut, $worked) : 0;
        $overtime = $checkOut !== null ? $this->overtimeMinutes($schedule, $worked) : 0;

        $status = match (true) {
            $checkOut === null && $dayClosed => AttendanceStatus::Incomplete,
            $late > 0 => AttendanceStatus::Late,
            $early > 0 => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };

        return new AttendanceMetrics($status, $worked, $late, $early, $overtime);
    }

    public function statusWithoutPresence(DaySchedule $schedule, bool $onLeave = false): AttendanceStatus
    {
        return match (true) {
            $schedule->isHoliday() => AttendanceStatus::Holiday,
            ! $schedule->isWorkingDay => AttendanceStatus::Weekend,
            $onLeave => AttendanceStatus::OnLeave,
            default => AttendanceStatus::Absent,
        };
    }

    public function lateMinutes(DaySchedule $schedule, CarbonImmutable $checkIn): int
    {
        if ($schedule->flexible || $schedule->start === null) {
            return 0;
        }

        $threshold = $schedule->start->addMinutes($schedule->graceMinutes);

        if ($checkIn->lessThanOrEqualTo($threshold)) {
            return 0;
        }

        $from = $schedule->lateMode === LateMode::FromStart ? $schedule->start : $threshold;

        return $this->minutesBetween($from, $checkIn);
    }

    public function earlyLeaveMinutes(DaySchedule $schedule, CarbonImmutable $checkOut, int $worked): int
    {
        if ($schedule->flexible || $schedule->end === null) {
            // Flexible schedules: shortfall against the required daily minutes.
            return $schedule->requiredMinutes ? max(0, $schedule->requiredMinutes - $worked) : 0;
        }

        $limit = $schedule->end->subMinutes($schedule->earlyLeaveGraceMinutes);

        return $checkOut->lessThan($limit) ? $this->minutesBetween($checkOut, $schedule->end) : 0;
    }

    public function overtimeMinutes(DaySchedule $schedule, int $worked): int
    {
        if (! $schedule->overtimeEnabled || $schedule->requiredMinutes === null) {
            return 0;
        }

        $extra = $worked - $schedule->requiredMinutes;

        return $extra > 0 && $extra >= $schedule->overtimeThresholdMinutes ? $extra : 0;
    }

    public function workedMinutes(DaySchedule $schedule, CarbonImmutable $checkIn, CarbonImmutable $checkOut): int
    {
        $span = $this->minutesBetween($checkIn, $checkOut);

        if ($schedule->breakStart !== null && $schedule->breakEnd !== null) {
            $overlapStart = $checkIn->max($schedule->breakStart);
            $overlapEnd = $checkOut->min($schedule->breakEnd);
            $breakTaken = $overlapEnd->greaterThan($overlapStart) ? $this->minutesBetween($overlapStart, $overlapEnd) : 0;

            return max(0, $span - $breakTaken);
        }

        if ($schedule->breakMinutes > 0 && $span > $schedule->breakMinutes * 2) {
            return $span - $schedule->breakMinutes;
        }

        return $span;
    }

    private function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 60);
    }
}
