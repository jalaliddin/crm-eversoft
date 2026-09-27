<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceVendor: string
{
    use EnumHelpers;

    case Hikvision = 'hikvision';
    case Fake = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::Hikvision => 'Hikvision',
            self::Fake => 'Fake',
        };
    }
}
