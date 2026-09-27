<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFieldDefinitionResource;
use App\Models\CustomFieldDefinition;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok(CustomFieldDefinitionResource::collection(
            CustomFieldDefinition::query()
                ->when($request->query('entity_type'), fn ($q, $v) => $q->where('entity_type', $v))
                ->orderBy('entity_type')->orderBy('sort_order')->get(),
        ));
    }

    public function store(Request $request, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::enum(CustomFieldEntity::class)],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions')->where('organization_id', $tenant->id())->where('entity_type', $request->input('entity_type'))],
            ...$this->rules(),
        ]);

        return $this->created(CustomFieldDefinitionResource::make(CustomFieldDefinition::query()->create($data)));
    }

    public function update(Request $request, CustomFieldDefinition $customField): JsonResponse
    {
        // Key and entity are immutable: stored values reference them.
        $customField->update($request->validate($this->rules()));

        return $this->ok(CustomFieldDefinitionResource::make($customField), 'Field updated.');
    }

    public function destroy(CustomFieldDefinition $customField): JsonResponse
    {
        $customField->delete();

        return $this->ok(null, 'Field deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array', 'required_if:field_type,select'],
            'options.*.value' => ['required_with:options', 'string', 'max:100'],
            'options.*.label' => ['required_with:options', 'string', 'max:100'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
