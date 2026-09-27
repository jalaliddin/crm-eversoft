<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/** @mixin AuditLog */
class AuditLogResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => (object) ($this->old_values ?? []),
            'new_values' => (object) ($this->new_values ?? []),
            'user' => $this->userRef($this->whenLoaded('user', fn () => $this->user, null)),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
