<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeviceConnectionMode: string
{
    use EnumHelpers;

    case Direct = 'direct';
    case Bridge = 'bridge';
    // The terminal itself posts ISAPI event notifications (HTTP listening host) to the CRM.
    case Push = 'push';
    // A CRM page open in an office browser reads the terminal through the Eversoft Terminal Bridge extension.
    case Browser = 'browser';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Bridge => 'Bridge',
            self::Push => 'HTTP push',
            self::Browser => 'Office browser',
        };
    }

    /** Events reach the CRM from outside; the server never connects to the terminal. */
    public function receivesPushes(): bool
    {
        return $this !== self::Direct;
    }
}
