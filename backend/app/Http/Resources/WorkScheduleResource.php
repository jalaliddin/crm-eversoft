<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hr\WorkSchedule;
use Illuminate\Http\Request;

/** @mixin WorkSchedule */
class WorkScheduleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'timezone' => $this->timezone,
            'grace_minutes' => $this->grace_minutes,
            'early_leave_grace_minutes' => $this->early_leave_grace_minutes,
            'late_mode' => $this->late_mode?->value,
            'required_daily_minutes' => $this->required_daily_minutes,
            'overtime_enabled' => $this->overtime_enabled,
            'overtime_threshold_minutes' => $this->overtime_threshold_minutes,
            'is_default' => $this->is_default,
            'status' => $this->status?->value,
            'days' => $this->whenLoaded('days', fn () => $this->days->map(fn ($d) => [
                'day_of_week' => $d->day_of_week,
                'is_working_day' => $d->is_working_day,
                'start_time' => $d->start_time ? substr($d->start_time, 0, 5) : null,
                'end_time' => $d->end_time ? substr($d->end_time, 0, 5) : null,
                'break_start' => $d->break_start ? substr($d->break_start, 0, 5) : null,
                'break_end' => $d->break_end ? substr($d->break_end, 0, 5) : null,
                'break_minutes' => $d->break_minutes,
            ])->values()),
            'employees_count' => $this->when(isset($this->employees_count), fn () => (int) $this->employees_count),
        ];
    }
}
