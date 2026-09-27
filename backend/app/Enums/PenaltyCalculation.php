<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PenaltyCalculation: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case PerMinute = 'per_minute';
    case PercentOfDaily = 'percent_of_daily';
    case DailySalary = 'daily_salary';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::PerMinute => 'Per minute',
            self::PercentOfDaily => 'Percent of daily',
            self::DailySalary => 'Daily salary',
        };
    }
}
