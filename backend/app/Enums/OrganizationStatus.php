<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum OrganizationStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Trial = 'trial';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Trial => 'Trial',
            self::Suspended => 'Suspended',
        };
    }
}
