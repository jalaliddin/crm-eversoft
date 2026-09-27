<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;

/** @mixin Organization */
class OrganizationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'logo_url' => $this->logoUrl(),
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'status' => $this->status?->value,
            'settings' => $this->resolvedSettings(),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
