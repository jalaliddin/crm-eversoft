<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EmploymentType: string
{
    use EnumHelpers;

    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full time',
            self::PartTime => 'Part time',
            self::Contract => 'Contract',
            self::Temporary => 'Temporary',
        };
    }
}
