<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attendance\AttendanceDevice;
use Illuminate\Http\Request;

/** @mixin AttendanceDevice */
class AttendanceDeviceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vendor' => $this->vendor?->value,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'type' => $this->type?->value,
            'connection_mode' => $this->connection_mode?->value,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'protocol' => $this->protocol,
            'username' => $this->username,
            // The password is write-only; clients only learn whether one is set.
            'has_password' => filled($this->resource->getRawOriginal('encrypted_password')),
            'has_bridge_token' => $this->bridge_token_hash !== null,
            'status' => $this->status?->value,
            'last_sync_at' => $this->iso($this->last_sync_at),
            'last_event_at' => $this->iso($this->last_event_at),
            'last_sync_status' => $this->last_sync_status,
            'last_error' => $this->last_error,
            'settings' => (object) array_intersect_key((array) $this->settings, array_flip(['timezone'])),
            'identifiers_count' => $this->whenCounted('identifiers'),
            'unmatched_events_count' => $this->when(isset($this->unmatched_events_count), fn () => (int) $this->unmatched_events_count),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
