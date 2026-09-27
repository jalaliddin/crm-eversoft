<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Fake;

use App\Enums\DeviceEventType;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Services\Attendance\Devices\AttendanceDeviceInterface;
use App\Services\Attendance\Devices\DeviceConnectionException;
use App\Services\Attendance\Devices\DeviceConnectionResult;
use App\Services\Attendance\Devices\DeviceEvent;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Mock adapter for development and tests. Configured through device settings:
 *   settings.offline = true           → every call fails like an unreachable device
 *   settings.events  = [{time, employee_identifier, card_number?, type?, external_id?}, ...]
 * It never pretends to be real hardware: the vendor is "fake" and visible in the UI.
 */
class FakeAttendanceDevice implements AttendanceDeviceInterface
{
    /** @var list<string> identifiers "pushed" during this instance's lifetime (for tests) */
    public array $pushed = [];

    /** @var list<string> */
    public array $removed = [];

    public function __construct(private readonly AttendanceDevice $device) {}

    public function testConnection(): DeviceConnectionResult
    {
        if ($this->offline()) {
            return new DeviceConnectionResult(false, 'Device unreachable (simulated).');
        }

        return new DeviceConnectionResult(true, 'Connected to fake device.', 1, ['model' => 'Fake terminal', 'serial_number' => 'FAKE-'.$this->device->id]);
    }

    public function getEvents(Carbon $from, Carbon $to): iterable
    {
        if ($this->offline()) {
            throw new DeviceConnectionException('Device unreachable (simulated).');
        }

        foreach ((array) ($this->device->settings['events'] ?? []) as $event) {
            $time = CarbonImmutable::parse($event['time'])->utc();
            if ($time->lessThan($from) || $time->greaterThan($to)) {
                continue;
            }

            yield new DeviceEvent(
                time: $time,
                employeeIdentifier: isset($event['employee_identifier']) ? (string) $event['employee_identifier'] : null,
                cardNumber: $event['card_number'] ?? null,
                type: DeviceEventType::tryFrom($event['type'] ?? '') ?? DeviceEventType::Unknown,
                externalId: isset($event['external_id']) ? (string) $event['external_id'] : null,
                payload: $event,
            );
        }
    }

    public function syncEmployees(): void
    {
        if ($this->offline()) {
            throw new DeviceConnectionException('Device unreachable (simulated).');
        }
    }

    public function pushEmployee(Employee $employee): void
    {
        if ($this->offline()) {
            throw new DeviceConnectionException('Device unreachable (simulated).');
        }
        $this->pushed[] = (string) $employee->id;
    }

    public function removeEmployee(Employee $employee): void
    {
        if ($this->offline()) {
            throw new DeviceConnectionException('Device unreachable (simulated).');
        }
        $this->removed[] = (string) $employee->id;
    }

    private function offline(): bool
    {
        return (bool) ($this->device->settings['offline'] ?? false);
    }
}
