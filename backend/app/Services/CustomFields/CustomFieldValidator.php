<?php

declare(strict_types=1);

namespace App\Services\CustomFields;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates and normalizes custom_fields payloads against the tenant's field definitions.
 * Unknown keys are dropped so clients cannot store arbitrary data.
 */
class CustomFieldValidator
{
    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    public function validate(CustomFieldEntity $entity, ?array $values, bool $partial = false): array
    {
        $definitions = CustomFieldDefinition::query()->where('entity_type', $entity->value)->orderBy('sort_order')->get();
        $values ??= [];

        $rules = [];
        foreach ($definitions as $definition) {
            $key = $definition->key;
            $base = $definition->is_required && ! $partial ? ['required'] : ['nullable'];

            $rules[$key] = [...$base, ...match ($definition->field_type) {
                CustomFieldType::Text => ['string', 'max:255'],
                CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric'],
                CustomFieldType::Date => ['date'],
                CustomFieldType::Boolean => ['boolean'],
                CustomFieldType::Select => ['string', Rule::in(array_column((array) $definition->options, 'value') ?: (array) $definition->options)],
            }];
        }

        $validated = Validator::make($values, $rules, [], $definitions->pluck('label', 'key')->all())
            ->validate();

        return array_intersect_key($validated, array_flip($definitions->pluck('key')->all()));
    }
}
