<?php

declare(strict_types=1);

namespace App\Services\Attendance\Data;

use App\Enums\AttendanceStatus;

final readonly class AttendanceMetrics
{
    public function __construct(
        public AttendanceStatus $status,
        public int $workedMinutes = 0,
        public int $lateMinutes = 0,
        public int $earlyLeaveMinutes = 0,
        public int $overtimeMinutes = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'worked_minutes' => $this->workedMinutes,
            'late_minutes' => $this->lateMinutes,
            'early_leave_minutes' => $this->earlyLeaveMinutes,
            'overtime_minutes' => $this->overtimeMinutes,
        ];
    }
}
