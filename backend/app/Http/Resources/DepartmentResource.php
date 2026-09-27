<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hr\Department;
use Illuminate\Http\Request;

/** @mixin Department */
class DepartmentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status?->value,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? ['id' => $this->parent->id, 'name' => $this->parent->name] : null),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? ['id' => $this->manager->id, 'full_name' => $this->manager->full_name] : null),
            'employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
