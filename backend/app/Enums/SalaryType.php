<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SalaryType: string
{
    use EnumHelpers;

    case Monthly = 'monthly';
    case Daily = 'daily';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Daily => 'Daily',
            self::Hourly => 'Hourly',
        };
    }
}
