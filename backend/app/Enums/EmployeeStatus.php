<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EmployeeStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Terminated => 'Terminated',
            self::OnLeave => 'On leave',
        };
    }
}
