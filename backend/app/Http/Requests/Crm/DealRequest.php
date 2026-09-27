<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;
use App\Support\Validation\TenantRule;

class DealRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => [...$this->requiredOnCreate(), 'string', 'max:255'],
            'value' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'probability' => ['sometimes', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers')],
            'lead_id' => ['nullable', 'integer', TenantRule::exists('leads')],
            'pipeline_id' => ['nullable', 'integer', TenantRule::exists('pipelines', softDeletes: false)],
            'pipeline_stage_id' => ['nullable', 'integer', TenantRule::exists('pipeline_stages', softDeletes: false)],
            'assigned_to' => ['nullable', 'integer', TenantRule::user()],
            'description' => ['nullable', 'string', 'max:10000'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', TenantRule::exists('tags', softDeletes: false)],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
