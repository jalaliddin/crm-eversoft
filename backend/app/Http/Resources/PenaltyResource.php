<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payroll\Penalty;
use Illuminate\Http\Request;

/** @mixin Penalty */
class PenaltyResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? ['id' => $this->employee->id, 'full_name' => $this->employee->full_name, 'employee_number' => $this->employee->employee_number] : null),
            'attendance_record_id' => $this->attendance_record_id,
            'rule' => $this->whenLoaded('rule', fn () => $this->rule ? ['id' => $this->rule->id, 'name' => $this->rule->name] : null),
            'date' => $this->day($this->date),
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'is_automatic' => $this->is_automatic,
            'applied_by' => $this->userRef($this->whenLoaded('appliedBy', fn () => $this->appliedBy, null)),
            'applied_at' => $this->iso($this->applied_at),
            'approved_by' => $this->userRef($this->whenLoaded('approvedBy', fn () => $this->approvedBy, null)),
            'approved_at' => $this->iso($this->approved_at),
        ];
    }
}
