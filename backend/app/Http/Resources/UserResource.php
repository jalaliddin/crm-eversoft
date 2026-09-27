<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl(),
            'locale' => $this->locale,
            'status' => $this->status?->value,
            'platform_role' => $this->platform_role?->value,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => ['id' => $r->id, 'slug' => $r->slug, 'name' => $r->name])->values()),
            'employee_id' => $this->whenLoaded('employee', fn () => $this->employee?->id),
            'last_login_at' => $this->iso($this->last_login_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
