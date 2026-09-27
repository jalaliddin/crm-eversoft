<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AttendanceCloseDayCommand extends Command
{
    protected $signature = 'attendance:close-day
        {--date= : Business date (Y-m-d). Default: yesterday in each organization timezone}
        {--organization= : Only this organization id}
        {--force : Close again even if the day was already closed}';

    protected $description = 'Finalize a business day: mark absences and incomplete records';

    public function handle(AttendanceProcessor $processor): int
    {
        $organizations = Organization::query()
            ->whereIn('status', [OrganizationStatus::Active->value, OrganizationStatus::Trial->value])
            ->when($this->option('organization'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($organizations as $organization) {
            $date = $this->option('date')
                ? CarbonImmutable::parse($this->option('date'))
                : CarbonImmutable::now($organization->timezone)->subDay()->startOfDay();

            $key = sprintf('attendance:closed:%d:%s', $organization->id, $date->format('Y-m-d'));
            if (! $this->option('force') && Cache::has($key)) {
                continue;
            }

            $stats = $processor->closeDay($organization, $date);
            Cache::put($key, true, now()->addDays(3));
            $this->line(sprintf('%s %s: absent %d, incomplete %d, on leave %d', $organization->name, $date->format('Y-m-d'), $stats['absent'], $stats['incomplete'], $stats['on_leave']));
        }

        return self::SUCCESS;
    }
}
