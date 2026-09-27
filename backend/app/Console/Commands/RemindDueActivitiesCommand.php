<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Models\Crm\Activity;
use App\Models\Organization;
use App\Notifications\TaskDueNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RemindDueActivitiesCommand extends Command
{
    protected $signature = 'activities:remind-due {--minutes=15 : Remind this many minutes before the due time}';

    protected $description = 'Notify assignees about activities that are due soon';

    public function handle(): int
    {
        $until = now()->addMinutes((int) $this->option('minutes'));
        $sent = 0;

        Activity::withoutGlobalScopes()
            ->with(['assignee', 'organization'])
            ->whereIn('status', [ActivityStatus::Pending->value, ActivityStatus::InProgress->value])
            ->whereNull('reminded_at')
            ->whereNotNull('assigned_to')
            ->whereBetween('due_at', [now()->subHour(), $until])
            ->chunkById(500, function ($activities) use (&$sent): void {
                foreach ($activities as $activity) {
                    /** @var Organization|null $organization */
                    $organization = $activity->organization;
                    if ($activity->assignee === null || $organization === null || ! $organization->isActive()) {
                        continue;
                    }

                    $activity->assignee->notify(new TaskDueNotification($activity->organization_id, [
                        'title' => $activity->title,
                        'activity_id' => $activity->id,
                        'due_at' => CarbonImmutable::instance($activity->due_at)->setTimezone($organization->timezone)->format('Y-m-d H:i'),
                        'link' => '/crm/activities?activity='.$activity->id,
                    ]));
                    $activity->forceFill(['reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("{$sent} reminders sent.");

        return self::SUCCESS;
    }
}
