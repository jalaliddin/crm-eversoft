<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices;

use App\Models\Hr\Employee;
use Carbon\Carbon;

/**
 * Vendor-neutral contract for biometric / access-control terminals.
 * Implementations must never touch attendance business logic — they only talk to the device.
 */
interface AttendanceDeviceInterface
{
    public function testConnection(): DeviceConnectionResult;

    /**
     * @return iterable<DeviceEvent>
     *
     * @throws DeviceConnectionException
     */
    public function getEvents(Carbon $from, Carbon $to): iterable;

    /** Push all mapped active employees to the device and remove inactive ones. */
    public function syncEmployees(): void;

    public function pushEmployee(Employee $employee): void;

    public function removeEmployee(Employee $employee): void;
}
