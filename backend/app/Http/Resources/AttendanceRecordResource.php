<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attendance\AttendanceRecord;
use Illuminate\Http\Request;

/** @mixin AttendanceRecord */
class AttendanceRecordResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_number' => $this->employee->employee_number,
                'department' => $this->employee->relationLoaded('department') ? $this->employee->department?->name : null,
            ]),
            'date' => $this->day($this->date),
            'schedule' => $this->whenLoaded('schedule', fn () => $this->schedule ? ['id' => $this->schedule->id, 'name' => $this->schedule->name] : null),
            'scheduled_start' => $this->iso($this->scheduled_start),
            'scheduled_end' => $this->iso($this->scheduled_end),
            'check_in' => $this->iso($this->actual_check_in),
            'check_out' => $this->iso($this->actual_check_out),
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status?->value,
            'source' => $this->source?->value,
            'device' => $this->whenLoaded('device', fn () => $this->device ? ['id' => $this->device->id, 'name' => $this->device->name] : null),
            'notes' => $this->notes,
            'is_manual' => $this->is_manual,
            'edited_by' => $this->userRef($this->whenLoaded('editor', fn () => $this->editor, null)),
            'edited_at' => $this->iso($this->edited_at),
            'edit_reason' => $this->edit_reason,
            'audit_logs' => $this->whenLoaded('auditLogs', fn () => $this->auditLogs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'old_values' => (object) ($log->old_values ?? []),
                'new_values' => (object) ($log->new_values ?? []),
                'reason' => $log->reason,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at' => $this->iso($log->created_at),
            ])->values()),
        ];
    }
}
