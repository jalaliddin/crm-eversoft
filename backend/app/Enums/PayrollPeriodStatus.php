<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PayrollPeriodStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Calculated => 'Calculated',
            self::Approved => 'Approved',
            self::Closed => 'Closed',
        };
    }
}
