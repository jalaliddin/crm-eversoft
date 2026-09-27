<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ActivityType: string
{
    use EnumHelpers;

    case Task = 'task';
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Task',
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Email => 'Email',
            self::Note => 'Note',
        };
    }
}
