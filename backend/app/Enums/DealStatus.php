<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DealStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }
}
