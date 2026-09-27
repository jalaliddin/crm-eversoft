<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\Pipeline;
use Illuminate\Http\Request;

/** @mixin Pipeline */
class PipelineResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($s) => [
                'id' => $s->id,
                'key' => $s->key,
                'name' => $s->name,
                'color' => $s->color,
                'probability' => $s->probability,
                'type' => $s->type->value,
                'sort_order' => $s->sort_order,
            ])->values()),
            'deals_count' => $this->whenCounted('deals'),
        ];
    }
}
