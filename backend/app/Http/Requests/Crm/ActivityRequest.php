<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Http\Requests\ApiFormRequest;
use App\Support\Validation\TenantRule;
use Illuminate\Validation\Rule;

class ActivityRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => [...$this->requiredOnCreate(), 'string', 'max:255'],
            'type' => [...$this->requiredOnCreate(), Rule::enum(ActivityType::class)],
            'description' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'priority' => ['sometimes', Rule::enum(ActivityPriority::class)],
            'status' => ['sometimes', Rule::enum(ActivityStatus::class)],
            'assigned_to' => ['nullable', 'integer', TenantRule::user()],
            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers')],
            'lead_id' => ['nullable', 'integer', TenantRule::exists('leads')],
            'deal_id' => ['nullable', 'integer', TenantRule::exists('deals')],
        ];
    }
}
