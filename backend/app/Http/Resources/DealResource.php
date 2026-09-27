<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\Deal;
use Illuminate\Http\Request;

/** @mixin Deal */
class DealResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'probability' => $this->probability,
            'expected_close_date' => $this->day($this->expected_close_date),
            'description' => $this->description,
            'lost_reason' => $this->lost_reason,
            'position' => $this->position,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'pipeline_id' => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'stage' => $this->whenLoaded('stage', fn () => ['id' => $this->stage->id, 'name' => $this->stage->name, 'key' => $this->stage->key, 'color' => $this->stage->color, 'type' => $this->stage->type->value]),
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'display_name' => $this->customer->display_name] : null),
            'lead_id' => $this->lead_id,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->userRef($this->whenLoaded('assignee', fn () => $this->assignee, null)),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'won_at' => $this->iso($this->won_at),
            'lost_at' => $this->iso($this->lost_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
