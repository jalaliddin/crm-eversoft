<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\Hr\WorkSchedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkScheduleService
{
    /** @param array<string, mixed> $data */
    public function save(?WorkSchedule $schedule, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule ??= new WorkSchedule;
            $schedule->fill(Arr::except($data, ['days']))->save();

            if (! empty($data['is_default'])) {
                WorkSchedule::query()->whereKeyNot($schedule->id)->update(['is_default' => false]);
            }

            if (isset($data['days'])) {
                $this->saveDays($schedule, $data['days']);
            }

            return $schedule->load('days');
        });
    }

    public function delete(WorkSchedule $schedule): void
    {
        if ($schedule->assignments()->whereNull('effective_to')->exists()) {
            throw new BusinessRuleException('This schedule is assigned to employees. Reassign them first.');
        }
        if ($schedule->is_default) {
            throw new BusinessRuleException('The default schedule cannot be deleted. Make another schedule the default first.');
        }

        $schedule->delete();
    }

    /** @param list<array<string, mixed>> $days */
    private function saveDays(WorkSchedule $schedule, array $days): void
    {
        $seen = [];
        foreach ($days as $day) {
            $dow = (int) $day['day_of_week'];
            $seen[] = $dow;
            $working = (bool) ($day['is_working_day'] ?? false);

            $schedule->days()->updateOrCreate(['day_of_week' => $dow], [
                'is_working_day' => $working,
                'start_time' => $working ? ($day['start_time'] ?? null) : null,
                'end_time' => $working ? ($day['end_time'] ?? null) : null,
                'break_start' => $working ? ($day['break_start'] ?? null) : null,
                'break_end' => $working ? ($day['break_end'] ?? null) : null,
                'break_minutes' => $working ? ($day['break_minutes'] ?? null) : null,
            ]);
        }

        $schedule->days()->whereNotIn('day_of_week', $seen)->delete();
    }
}
