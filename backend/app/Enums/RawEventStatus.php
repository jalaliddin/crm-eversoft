<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RawEventStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Processed = 'processed';
    case Unmatched = 'unmatched';
    case Failed = 'failed';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processed => 'Processed',
            self::Unmatched => 'Unmatched',
            self::Failed => 'Failed',
            self::Ignored => 'Ignored',
        };
    }
}
