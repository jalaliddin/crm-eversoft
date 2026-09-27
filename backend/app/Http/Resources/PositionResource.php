<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hr\Position;
use Illuminate\Http\Request;

/** @mixin Position */
class PositionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status?->value,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
