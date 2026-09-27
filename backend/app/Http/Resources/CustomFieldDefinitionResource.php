<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;

/** @mixin CustomFieldDefinition */
class CustomFieldDefinitionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type->value,
            'key' => $this->key,
            'label' => $this->label,
            'field_type' => $this->field_type->value,
            'options' => $this->options ?? [],
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];
    }
}
