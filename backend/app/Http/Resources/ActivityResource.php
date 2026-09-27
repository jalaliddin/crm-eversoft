<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\Activity;
use Illuminate\Http\Request;

/** @mixin Activity */
class ActivityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type?->value,
            'description' => $this->description,
            'due_at' => $this->iso($this->due_at),
            'completed_at' => $this->iso($this->completed_at),
            'duration_minutes' => $this->duration_minutes,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'is_overdue' => $this->isOpen() && $this->due_at !== null && $this->due_at->isPast(),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->userRef($this->whenLoaded('assignee', fn () => $this->assignee, null)),
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'display_name' => $this->customer->display_name] : null),
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? ['id' => $this->lead->id, 'name' => $this->lead->name] : null),
            'deal_id' => $this->deal_id,
            'deal' => $this->whenLoaded('deal', fn () => $this->deal ? ['id' => $this->deal->id, 'title' => $this->deal->title] : null),
            'created_by' => $this->userRef($this->whenLoaded('creator', fn () => $this->creator, null)),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
