<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr;

use App\Enums\LateMode;
use App\Enums\RecordStatus;
use App\Enums\ScheduleType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class WorkScheduleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $flexible = $this->input('type', $this->route('work_schedule')?->type?->value) === 'flexible';
        $timeRequired = $flexible ? [] : ['required_if:days.*.is_working_day,true'];

        return [
            'name' => [...$this->requiredOnCreate(), 'string', 'max:255'],
            'type' => [...$this->requiredOnCreate(), Rule::enum(ScheduleType::class)],
            'timezone' => ['nullable', 'timezone:all'],
            'grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'early_leave_grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'late_mode' => ['sometimes', Rule::enum(LateMode::class)],
            'required_daily_minutes' => ['nullable', 'integer', 'between:1,1440', 'required_if:type,flexible'],
            'overtime_enabled' => ['sometimes', 'boolean'],
            'overtime_threshold_minutes' => ['sometimes', 'integer', 'between:0,600'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
            'days' => [...$this->requiredOnCreate(), 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.is_working_day' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i', ...$timeRequired],
            'days.*.end_time' => ['nullable', 'date_format:H:i', ...$timeRequired],
            'days.*.break_start' => ['nullable', 'date_format:H:i', 'required_with:days.*.break_end'],
            'days.*.break_end' => ['nullable', 'date_format:H:i', 'required_with:days.*.break_start'],
            'days.*.break_minutes' => ['nullable', 'integer', 'between:0,600'],
        ];
    }
}
