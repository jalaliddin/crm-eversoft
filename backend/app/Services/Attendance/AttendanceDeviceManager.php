<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\Devices\AttendanceDeviceInterface;
use App\Services\Attendance\Devices\Fake\FakeAttendanceDevice;
use App\Services\Attendance\Devices\Hikvision\HikvisionAttendanceDevice;
use App\Services\Attendance\Devices\Hikvision\HikvisionClient;
use App\Services\Attendance\Devices\Hikvision\HikvisionEventMapper;
use Closure;
use InvalidArgumentException;

/**
 * Resolves the vendor adapter for a device. New vendors (ZKTeco, Suprema, ...) register a driver here;
 * nothing else in the attendance engine changes.
 */
class AttendanceDeviceManager
{
    /** @var array<string, Closure(AttendanceDevice): AttendanceDeviceInterface> */
    private array $drivers = [];

    public function __construct()
    {
        $this->extend('hikvision', fn (AttendanceDevice $device) => new HikvisionAttendanceDevice(
            $device,
            new HikvisionClient($device),
            new HikvisionEventMapper,
        ));
        $this->extend('fake', fn (AttendanceDevice $device) => new FakeAttendanceDevice($device));
    }

    /** @param Closure(AttendanceDevice): AttendanceDeviceInterface $factory */
    public function extend(string $vendor, Closure $factory): void
    {
        $this->drivers[$vendor] = $factory;
    }

    public function for(AttendanceDevice $device): AttendanceDeviceInterface
    {
        $vendor = $device->vendor->value;

        if (! isset($this->drivers[$vendor])) {
            throw new InvalidArgumentException("No attendance driver registered for vendor [{$vendor}].");
        }

        return ($this->drivers[$vendor])($device);
    }

    /** @return list<string> */
    public function vendors(): array
    {
        return array_keys($this->drivers);
    }
}
