<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Http\Requests\ApiFormRequest;
use App\Support\Validation\TenantRule;
use Illuminate\Validation\Rule;

class CustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'type' => [...$this->requiredOnCreate(), Rule::enum(CustomerType::class)],
            'first_name' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $this->input('type') === 'individual' && ! $this->isUpdate())],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->input('type') === 'company' && ! $this->isUpdate())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'secondary_phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['nullable', 'integer', TenantRule::user()],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', TenantRule::exists('tags', softDeletes: false)],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
