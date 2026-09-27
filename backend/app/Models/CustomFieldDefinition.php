<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['entity_type', 'key', 'label', 'field_type', 'options', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return [
            'entity_type' => CustomFieldEntity::class,
            'field_type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }
}
