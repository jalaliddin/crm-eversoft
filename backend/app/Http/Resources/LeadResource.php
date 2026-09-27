<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\Lead;
use Illuminate\Http\Request;

/** @mixin Lead */
class LeadResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source,
            'status' => $this->status?->value,
            'expected_value' => $this->expected_value !== null ? (float) $this->expected_value : null,
            'description' => $this->description,
            'lost_reason' => $this->lost_reason,
            'position' => $this->position,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'display_name' => $this->customer->display_name] : null),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->userRef($this->whenLoaded('assignee', fn () => $this->assignee, null)),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'converted_at' => $this->iso($this->converted_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
