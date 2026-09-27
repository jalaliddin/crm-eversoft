<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use App\Models\Hr\WorkSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use BelongsToOrganization;

    /** Attributes tracked by attendance_audit_logs. */
    public const AUDITED = [
        'actual_check_in', 'actual_check_out', 'status', 'worked_minutes', 'late_minutes',
        'early_leave_minutes', 'overtime_minutes', 'notes', 'source',
    ];

    protected $fillable = [
        'employee_id', 'date', 'work_schedule_id', 'scheduled_start', 'scheduled_end', 'actual_check_in',
        'actual_check_out', 'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'status',
        'source', 'device_id', 'raw_event_id', 'notes', 'is_manual', 'edited_by', 'edited_at', 'edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'actual_check_in' => 'datetime',
            'actual_check_out' => 'datetime',
            'edited_at' => 'datetime',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'is_manual' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AttendanceAuditLog::class)->latest('id');
    }
}
