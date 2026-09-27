<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Services\Attendance\Devices\DeviceConnectionException;

class HikvisionRequestException extends DeviceConnectionException
{
    public function __construct(string $message, public readonly int $status, public readonly ?string $subStatusCode = null)
    {
        parent::__construct($message);
    }
}
