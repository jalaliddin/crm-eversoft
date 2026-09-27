<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ScheduleType: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Shift = 'shift';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Shift => 'Shift',
            self::Flexible => 'Flexible',
        };
    }
}
