<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\TimelineEvent;
use Illuminate\Http\Request;

/** @mixin TimelineEvent */
class TimelineEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'properties' => (object) ($this->properties ?? []),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'customer_id' => $this->customer_id,
            'lead_id' => $this->lead_id,
            'deal_id' => $this->deal_id,
            'causer' => $this->userRef($this->whenLoaded('causer', fn () => $this->causer, null)),
            'occurred_at' => $this->iso($this->occurred_at),
        ];
    }
}
