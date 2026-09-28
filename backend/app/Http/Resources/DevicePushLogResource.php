<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attendance\AttendanceDevicePushLog;
use Illuminate\Http\Request;

/** @mixin AttendanceDevicePushLog */
class DevicePushLogResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device' => $this->whenLoaded('device', fn () => $this->device ? ['id' => $this->device->id, 'name' => $this->device->name] : null),
            'status' => $this->status,
            'event_type' => $this->event_type,
            'events_count' => $this->events_count,
            'stored_count' => $this->stored_count,
            'summary' => $this->summary,
            'content_type' => $this->content_type,
            'remote_ip' => $this->remote_ip,
            'body_excerpt' => $this->body_excerpt,
            'error' => $this->error,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
