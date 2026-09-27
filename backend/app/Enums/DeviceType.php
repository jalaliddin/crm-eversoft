<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceType: string
{
    use EnumHelpers;

    case FaceTerminal = 'face_terminal';
    case Fingerprint = 'fingerprint';
    case CardReader = 'card_reader';

    public function label(): string
    {
        return match ($this) {
            self::FaceTerminal => 'Face terminal',
            self::Fingerprint => 'Fingerprint',
            self::CardReader => 'Card reader',
        };
    }
}
