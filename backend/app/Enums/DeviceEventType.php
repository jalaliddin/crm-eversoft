<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceEventType: string
{
    use EnumHelpers;

    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Check in',
            self::CheckOut => 'Check out',
            self::Unknown => 'Unknown',
        };
    }
}
