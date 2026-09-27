<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\Customer;
use Illuminate\Http\Request;

/** @mixin Customer */
class CustomerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'source' => $this->source,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'owner' => $this->userRef($this->whenLoaded('owner', fn () => $this->owner, null)),
            'owner_id' => $this->owner_id,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'leads_count' => $this->whenCounted('leads'),
            'deals_count' => $this->whenCounted('deals'),
            'open_deals_value' => $this->when(isset($this->open_deals_value), fn () => (float) $this->open_deals_value),
            'created_by' => $this->userRef($this->whenLoaded('creator', fn () => $this->creator, null)),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
