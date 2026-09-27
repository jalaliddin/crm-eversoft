<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PenaltyType: string
{
    use EnumHelpers;

    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case Absence = 'absence';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Late => 'Late',
            self::EarlyLeave => 'Early leave',
            self::Absence => 'Absence',
            self::Manual => 'Manual',
        };
    }
}
