<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAuditLog extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = ['attendance_record_id', 'employee_id', 'date', 'action', 'old_values', 'new_values', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
