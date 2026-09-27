<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum LateMode: string
{
    use EnumHelpers;

    case AfterGrace = 'after_grace';
    case FromStart = 'from_start';

    public function label(): string
    {
        return match ($this) {
            self::AfterGrace => 'After grace',
            self::FromStart => 'From start',
        };
    }
}
