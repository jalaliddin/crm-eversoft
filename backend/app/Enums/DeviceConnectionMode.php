<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceConnectionMode: string
{
    use EnumHelpers;

    case Direct = 'direct';
    case Bridge = 'bridge';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Bridge => 'Bridge',
        };
    }
}
