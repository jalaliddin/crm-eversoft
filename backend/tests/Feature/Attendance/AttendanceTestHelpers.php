<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Hr\Employee;
use App\Models\Organization;
use Carbon\CarbonImmutable;

trait AttendanceTestHelpers
{
    /** Monday 2026-09-21 — the reference business date for integration tests. */
    protected string $monday = '2026-09-21';

    protected function fakeDevice(Organization $organization, array $events = [], array $attributes = []): AttendanceDevice
    {
        return $this->inTenant($organization, fn () => AttendanceDevice::query()->create([
            'name' => 'Fake', 'vendor' => 'fake', 'status' => 'active', 'connection_mode' => 'direct',
            'settings' => ['events' => $events], ...$attributes,
        ]));
    }

    protected function mappedEmployee(Organization $organization, string $identifier, array $attributes = []): Employee
    {
        return $this->inTenant($organization, function () use ($identifier, $attributes) {
            $employee = Employee::factory()->create(['hire_date' => '2025-01-01', ...$attributes]);
            EmployeeDeviceIdentifier::query()->create(['employee_id' => $employee->id, 'identifier' => $identifier, 'status' => 'active']);

            return $employee;
        });
    }

    /** Local Tashkent time on the reference Monday, as an ISO string with offset. */
    protected function local(string $time, ?string $date = null): string
    {
        return CarbonImmutable::parse(($date ?? $this->monday).' '.$time, 'Asia/Tashkent')->toIso8601String();
    }
}
