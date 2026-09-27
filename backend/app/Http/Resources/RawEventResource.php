<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attendance\AttendanceRawEvent;
use Illuminate\Http\Request;

/** @mixin AttendanceRawEvent */
class RawEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device' => $this->whenLoaded('device', fn () => $this->device ? ['id' => $this->device->id, 'name' => $this->device->name] : null),
            'external_event_id' => $this->external_event_id,
            'employee_identifier' => $this->employee_identifier,
            'card_number' => $this->card_number,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? ['id' => $this->employee->id, 'full_name' => $this->employee->full_name] : null),
            'event_type' => $this->event_type?->value,
            'event_time' => $this->iso($this->event_time),
            'processing_status' => $this->processing_status?->value,
            'error_message' => $this->error_message,
            'name_on_device' => $this->payload['name'] ?? null,
            'processed_at' => $this->iso($this->processed_at),
        ];
    }
}
