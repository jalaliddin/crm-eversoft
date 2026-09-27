<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\RecordStatus;
use App\Models\Attendance\EmployeeDeviceIdentifier;

/**
 * Maps a device-side identifier (employeeNo / card) to an employee.
 * Device-specific mappings win over organization-wide ones. Never assumes identifier == employee id.
 */
class EmployeeIdentifierResolver
{
    /** @var array<string, ?int> */
    private array $cache = [];

    public function resolve(int $organizationId, int $deviceId, ?string $identifier, ?string $cardNumber): ?int
    {
        $key = implode('|', [$organizationId, $deviceId, $identifier, $cardNumber]);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $base = fn () => EmployeeDeviceIdentifier::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', RecordStatus::Active->value)
            ->where(fn ($q) => $q->where('device_id', $deviceId)->orWhereNull('device_id'))
            ->orderByRaw('device_id is null');

        $employeeId = null;
        if ($identifier !== null && $identifier !== '') {
            $employeeId = $base()->where('identifier', $identifier)->value('employee_id');
        }
        if ($employeeId === null && $cardNumber !== null && $cardNumber !== '') {
            $employeeId = $base()->where('card_number', $cardNumber)->value('employee_id');
        }

        return $this->cache[$key] = $employeeId !== null ? (int) $employeeId : null;
    }
}
