<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payroll\PayrollRecord;
use Illuminate\Http\Request;

/** @mixin PayrollRecord */
class PayrollRecordResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'period' => $this->whenLoaded('period', fn () => ['id' => $this->period->id, 'year' => $this->period->year, 'month' => $this->period->month, 'status' => $this->period->status->value]),
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_number' => $this->employee->employee_number,
                'department' => $this->employee->relationLoaded('department') ? $this->employee->department?->name : null,
            ] : null),
            'salary_type' => $this->salary_type?->value,
            'rate' => (float) $this->rate,
            'base_salary' => (float) $this->base_salary,
            'scheduled_days' => $this->scheduled_days,
            'scheduled_minutes' => $this->scheduled_minutes,
            'worked_days' => $this->worked_days,
            'worked_minutes' => $this->worked_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'absence_days' => $this->absence_days,
            'overtime_amount' => (float) $this->overtime_amount,
            'gross_amount' => (float) $this->gross_amount,
            'penalty_amount' => (float) $this->penalty_amount,
            'bonus_amount' => (float) $this->bonus_amount,
            'net_amount' => (float) $this->net_amount,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'details' => (object) ($this->details ?? []),
            'paid_at' => $this->iso($this->paid_at),
        ];
    }
}
