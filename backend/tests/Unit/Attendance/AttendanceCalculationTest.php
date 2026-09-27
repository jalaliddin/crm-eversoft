<?php

namespace Tests\Unit\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\LateMode;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\Data\DaySchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Schedule 08:30–17:30, break 13:00–14:00, grace 10 min, Asia/Tashkent (the spec's reference day). */
class AttendanceCalculationTest extends TestCase
{
    private const TZ = 'Asia/Tashkent';

    private const DATE = '2026-09-21'; // Monday

    private AttendanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AttendanceCalculator;
    }

    public function test_on_time_is_not_late(): void
    {
        $m = $this->calculator->calculate($this->day(), $this->at('08:30'), $this->at('17:30'));

        $this->assertSame(0, $m->lateMinutes);
        $this->assertSame(AttendanceStatus::Present, $m->status);
    }

    public function test_arrival_within_grace_is_not_late(): void
    {
        $this->assertSame(0, $this->calculator->calculate($this->day(), $this->at('08:35'), $this->at('17:30'))->lateMinutes);
        $this->assertSame(0, $this->calculator->calculate($this->day(), $this->at('08:40'), $this->at('17:30'))->lateMinutes);
    }

    public function test_late_after_grace_counts_minutes_beyond_grace(): void
    {
        $m = $this->calculator->calculate($this->day(), $this->at('08:45'), $this->at('17:30'));

        $this->assertSame(5, $m->lateMinutes);
        $this->assertSame(AttendanceStatus::Late, $m->status);
    }

    public function test_late_from_schedule_start_mode_counts_full_lateness(): void
    {
        $day = $this->day(['lateMode' => LateMode::FromStart]);

        $this->assertSame(15, $this->calculator->calculate($day, $this->at('08:45'), $this->at('17:30'))->lateMinutes);
        $this->assertSame(0, $this->calculator->calculate($day, $this->at('08:35'), $this->at('17:30'))->lateMinutes);
    }

    public function test_seconds_are_truncated(): void
    {
        $this->assertSame(5, $this->calculator->calculate($this->day(), $this->at('08:45:59'), $this->at('17:30'))->lateMinutes);
    }

    public function test_early_leave(): void
    {
        $m = $this->calculator->calculate($this->day(), $this->at('08:30'), $this->at('17:20'));

        $this->assertSame(10, $m->earlyLeaveMinutes);
        $this->assertSame(AttendanceStatus::EarlyLeave, $m->status);
    }

    public function test_early_leave_grace(): void
    {
        $day = $this->day(['earlyLeaveGraceMinutes' => 15]);

        $this->assertSame(0, $this->calculator->calculate($day, $this->at('08:30'), $this->at('17:20'))->earlyLeaveMinutes);
        $this->assertSame(20, $this->calculator->calculate($day, $this->at('08:30'), $this->at('17:10'))->earlyLeaveMinutes);
    }

    public function test_worked_time_excludes_break(): void
    {
        // Spec example: 08:42 → 17:20 with a 60 min break = 7h38m.
        $m = $this->calculator->calculate($this->day(), $this->at('08:42'), $this->at('17:20'));

        $this->assertSame(7 * 60 + 38, $m->workedMinutes);
    }

    public function test_only_the_overlapping_part_of_the_break_is_deducted(): void
    {
        // Left at 13:30: only 30 minutes of the break were inside the worked span.
        $this->assertSame(270 - 30, $this->calculator->calculate($this->day(), $this->at('09:00'), $this->at('13:30'))->workedMinutes);
        // Worked only in the morning: no break deduction.
        $this->assertSame(210, $this->calculator->calculate($this->day(), $this->at('08:30'), $this->at('12:00'))->workedMinutes);
    }

    public function test_break_minutes_without_window(): void
    {
        $day = $this->day(['breakStart' => null, 'breakEnd' => null, 'breakMinutes' => 45]);

        $this->assertSame(540 - 45, $this->calculator->calculate($day, $this->at('08:30'), $this->at('17:30'))->workedMinutes);
    }

    public function test_overtime_when_enabled(): void
    {
        $day = $this->day(['overtimeEnabled' => true]);

        $m = $this->calculator->calculate($day, $this->at('08:30'), $this->at('19:00'));
        $this->assertSame(570, $m->workedMinutes);
        $this->assertSame(90, $m->overtimeMinutes);

        $this->assertSame(0, $this->calculator->calculate($this->day(), $this->at('08:30'), $this->at('19:00'))->overtimeMinutes);
    }

    public function test_overtime_threshold(): void
    {
        $day = $this->day(['overtimeEnabled' => true, 'overtimeThresholdMinutes' => 30]);

        $this->assertSame(0, $this->calculator->calculate($day, $this->at('08:30'), $this->at('17:50'))->overtimeMinutes);
        $this->assertSame(40, $this->calculator->calculate($day, $this->at('08:30'), $this->at('18:10'))->overtimeMinutes);
    }

    public function test_missing_check_out(): void
    {
        // Day still running → "currently working"; late status still visible.
        $open = $this->calculator->calculate($this->day(), $this->at('08:50'), null, dayClosed: false);
        $this->assertSame(AttendanceStatus::Late, $open->status);
        $this->assertSame(10, $open->lateMinutes);
        $this->assertSame(0, $open->workedMinutes);

        $closed = $this->calculator->calculate($this->day(), $this->at('08:50'), null, dayClosed: true);
        $this->assertSame(AttendanceStatus::Incomplete, $closed->status);
    }

    public function test_statuses_without_presence(): void
    {
        $this->assertSame(AttendanceStatus::Absent, $this->calculator->calculate($this->day(), null, null)->status);
        $this->assertSame(AttendanceStatus::Weekend, $this->calculator->calculate($this->day(['isWorkingDay' => false]), null, null)->status);
        $this->assertSame(AttendanceStatus::Holiday, $this->calculator->calculate($this->day(['holidayName' => 'Navruz']), null, null)->status);
        $this->assertSame(AttendanceStatus::OnLeave, $this->calculator->calculate($this->day(), null, null, onLeave: true)->status);
        // A holiday wins over leave: leave days are not consumed on holidays.
        $this->assertSame(AttendanceStatus::Holiday, $this->calculator->calculate($this->day(['holidayName' => 'Navruz']), null, null, onLeave: true)->status);
    }

    public function test_work_on_day_off_counts_as_overtime(): void
    {
        $day = $this->day(['isWorkingDay' => false, 'overtimeEnabled' => true]);
        $m = $this->calculator->calculate($day, $this->at('10:00'), $this->at('14:00'));

        $this->assertSame(AttendanceStatus::Present, $m->status);
        $this->assertSame(0, $m->lateMinutes);
        $this->assertSame(180, $m->workedMinutes); // 4h minus the 13:00–14:00 break window
        $this->assertSame(180, $m->overtimeMinutes);
    }

    public function test_flexible_schedule_has_no_lateness_but_tracks_shortfall(): void
    {
        $day = $this->day(['flexible' => true, 'start' => null, 'end' => null, 'breakStart' => null, 'breakEnd' => null, 'breakMinutes' => 60, 'requiredMinutes' => 480]);

        $m = $this->calculator->calculate($day, $this->at('11:00'), $this->at('18:00'));
        $this->assertSame(0, $m->lateMinutes);
        $this->assertSame(360, $m->workedMinutes);
        $this->assertSame(120, $m->earlyLeaveMinutes);
    }

    public function test_overnight_shift(): void
    {
        $date = CarbonImmutable::parse(self::DATE, self::TZ);
        $day = new DaySchedule(
            date: $date,
            timezone: self::TZ,
            isWorkingDay: true,
            start: $date->setTime(22, 0),
            end: $date->addDay()->setTime(6, 0),
            graceMinutes: 0,
            requiredMinutes: 480,
        );

        $m = $this->calculator->calculate($day, $date->setTime(22, 20), $date->addDay()->setTime(5, 45));

        $this->assertTrue($day->crossesMidnight());
        $this->assertSame(20, $m->lateMinutes);
        $this->assertSame(15, $m->earlyLeaveMinutes);
        $this->assertSame(445, $m->workedMinutes);
    }

    public function test_check_out_before_check_in_is_ignored(): void
    {
        $m = $this->calculator->calculate($this->day(), $this->at('09:00'), $this->at('08:00'), dayClosed: true);

        $this->assertSame(AttendanceStatus::Incomplete, $m->status);
        $this->assertSame(0, $m->workedMinutes);
    }

    #[DataProvider('timezoneProvider')]
    public function test_calculations_use_the_schedule_timezone(string $utcCheckIn, int $expectedLate): void
    {
        $m = $this->calculator->calculate($this->day(), CarbonImmutable::parse($utcCheckIn, 'UTC'), null, dayClosed: false);

        $this->assertSame($expectedLate, $m->lateMinutes);
    }

    /** @return array<string, array{string, int}> */
    public static function timezoneProvider(): array
    {
        // Asia/Tashkent = UTC+5, so 08:45 local = 03:45 UTC.
        return [
            'on time (03:30Z)' => ['2026-09-21 03:30:00', 0],
            'late (03:45Z)' => ['2026-09-21 03:45:00', 5],
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function day(array $overrides = []): DaySchedule
    {
        $date = CarbonImmutable::parse(self::DATE, self::TZ);

        return new DaySchedule(...[
            'date' => $date,
            'timezone' => self::TZ,
            'isWorkingDay' => true,
            'holidayName' => null,
            'start' => $date->setTime(8, 30),
            'end' => $date->setTime(17, 30),
            'breakStart' => $date->setTime(13, 0),
            'breakEnd' => $date->setTime(14, 0),
            'breakMinutes' => 60,
            'graceMinutes' => 10,
            'earlyLeaveGraceMinutes' => 0,
            'lateMode' => LateMode::AfterGrace,
            'requiredMinutes' => 480,
            'overtimeEnabled' => false,
            'overtimeThresholdMinutes' => 0,
            'flexible' => false,
            'scheduleId' => 1,
            ...$overrides,
        ]);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DATE.' '.$time, self::TZ);
    }
}
