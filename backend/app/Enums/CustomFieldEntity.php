<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CustomFieldEntity: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Lead = 'lead';
    case Deal = 'deal';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Lead => 'Lead',
            self::Deal => 'Deal',
            self::Employee => 'Employee',
        };
    }
}
