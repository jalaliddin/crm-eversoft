<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttendanceStatus: string
{
    use EnumHelpers;

    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case EarlyLeave = 'early_leave';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::EarlyLeave => 'Early leave',
            self::OnLeave => 'On leave',
            self::Holiday => 'Holiday',
            self::Weekend => 'Weekend',
            self::Incomplete => 'Incomplete',
        };
    }
}
