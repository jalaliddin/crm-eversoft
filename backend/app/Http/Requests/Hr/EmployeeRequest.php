<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\SalaryType;
use App\Http\Requests\ApiFormRequest;
use App\Support\Validation\TenantRule;
use Illuminate\Validation\Rule;

class EmployeeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'employee_number' => ['sometimes', 'required', 'string', 'max:32', TenantRule::unique('employees', 'employee_number', $employee?->id)],
            'first_name' => [...$this->requiredOnCreate(), 'string', 'max:100'],
            'last_name' => [...$this->requiredOnCreate(), 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', TenantRule::exists('departments')],
            'position_id' => ['nullable', 'integer', TenantRule::exists('positions')],
            'manager_id' => ['nullable', 'integer', TenantRule::exists('employees'), Rule::notIn(array_filter([$employee?->id]))],
            'user_id' => ['nullable', 'integer', TenantRule::user()],
            'employment_type' => ['sometimes', Rule::enum(EmploymentType::class)],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'salary_type' => ['sometimes', Rule::enum(SalaryType::class)],
            'salary_amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'work_schedule_id' => ['nullable', 'integer', TenantRule::exists('work_schedules')],
            'device_identifier' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
