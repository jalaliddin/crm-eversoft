<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Enums\LateMode;
use App\Enums\RecordStatus;
use App\Enums\ScheduleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkSchedule extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'name', 'type', 'timezone', 'grace_minutes', 'early_leave_grace_minutes', 'late_mode',
        'required_daily_minutes', 'overtime_enabled', 'overtime_threshold_minutes', 'is_default', 'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScheduleType::class,
            'late_mode' => LateMode::class,
            'status' => RecordStatus::class,
            'grace_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
            'required_daily_minutes' => 'integer',
            'overtime_enabled' => 'boolean',
            'overtime_threshold_minutes' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('day_of_week');
    }

    public function dayFor(int $isoDayOfWeek): ?WorkScheduleDay
    {
        return $this->days->firstWhere('day_of_week', $isoDayOfWeek);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }
}
