<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\LeadStatus;
use App\Http\Requests\ApiFormRequest;
use App\Support\Validation\TenantRule;
use Illuminate\Validation\Rule;

class LeadRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [...$this->requiredOnCreate(), 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)->except([LeadStatus::Converted])],
            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers')],
            'assigned_to' => ['nullable', 'integer', TenantRule::user()],
            'expected_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:10000'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', TenantRule::exists('tags', softDeletes: false)],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
