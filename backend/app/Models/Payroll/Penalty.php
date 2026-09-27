<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Enums\PenaltyStatus;
use App\Enums\PenaltyType;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penalty extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'employee_id', 'attendance_record_id', 'penalty_rule_id', 'date', 'type', 'amount', 'percentage', 'reason',
        'status', 'is_automatic', 'applied_by', 'applied_at', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => PenaltyType::class,
            'status' => PenaltyStatus::class,
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_automatic' => 'boolean',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PenaltyRule::class, 'penalty_rule_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
