<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hr\Employee;
use Illuminate\Http\Request;

/** @mixin Employee */
class EmployeeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $canSeeSalary = $request->user()?->hasPermission('payroll.view') ?? false;
        $current = $this->relationLoaded('scheduleAssignments')
            ? $this->scheduleAssignments->first(fn ($a) => $a->effective_to === null || $a->effective_to->isFuture() || $a->effective_to->isToday())
            : null;

        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'birth_date' => $this->day($this->birth_date),
            'gender' => $this->gender?->value,
            'address' => $this->address,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded('position', fn () => $this->position ? ['id' => $this->position->id, 'name' => $this->position->name] : null),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? ['id' => $this->manager->id, 'full_name' => $this->manager->full_name] : null),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            'employment_type' => $this->employment_type?->value,
            'hire_date' => $this->day($this->hire_date),
            'termination_date' => $this->day($this->termination_date),
            // Compensation is visible only to users allowed to see payroll.
            'salary_type' => $this->salary_type?->value,
            'salary_amount' => $canSeeSalary ? (float) $this->salary_amount : null,
            'status' => $this->status?->value,
            'photo_url' => $this->photoUrl(),
            'notes' => $this->notes,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'work_schedule' => $this->when($current !== null, fn () => ['id' => $current->work_schedule_id, 'name' => $current->schedule?->name, 'effective_from' => $this->day($current->effective_from)]),
            'schedule_assignments' => $this->whenLoaded('scheduleAssignments', fn () => $this->scheduleAssignments->map(fn ($a) => [
                'id' => $a->id,
                'work_schedule_id' => $a->work_schedule_id,
                'name' => $a->schedule?->name,
                'effective_from' => $this->day($a->effective_from),
                'effective_to' => $this->day($a->effective_to),
            ])->values()),
            'device_identifiers' => $this->whenLoaded('deviceIdentifiers', fn () => DeviceIdentifierResource::collection($this->deviceIdentifiers)),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
