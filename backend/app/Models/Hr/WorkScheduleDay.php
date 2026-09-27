<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleDay extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'work_schedule_id', 'day_of_week', 'is_working_day', 'start_time', 'end_time',
        'break_start', 'break_end', 'break_minutes',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_working_day' => 'boolean',
            'break_minutes' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
