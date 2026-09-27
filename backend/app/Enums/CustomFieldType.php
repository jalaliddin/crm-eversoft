<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CustomFieldType: string
{
    use EnumHelpers;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Textarea',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Boolean => 'Boolean',
            self::Select => 'Select',
        };
    }
}
