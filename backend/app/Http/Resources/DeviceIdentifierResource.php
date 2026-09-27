<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attendance\EmployeeDeviceIdentifier;
use Illuminate\Http\Request;

/** @mixin EmployeeDeviceIdentifier */
class DeviceIdentifierResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? ['id' => $this->employee->id, 'full_name' => $this->employee->full_name] : null),
            'device_id' => $this->device_id,
            'device' => $this->whenLoaded('device', fn () => $this->device ? ['id' => $this->device->id, 'name' => $this->device->name] : null),
            'identifier' => $this->identifier,
            'card_number' => $this->card_number,
            'biometric_identifier' => $this->biometric_identifier,
            'status' => $this->status?->value,
        ];
    }
}
