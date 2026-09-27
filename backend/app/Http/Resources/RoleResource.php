<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;

/** @mixin Role */
class RoleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions' => $this->permissions(),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
