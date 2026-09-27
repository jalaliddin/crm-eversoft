<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\ActivityStatus;
use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function __construct(private readonly TimelineRecorder $timeline) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Activity
    {
        return DB::transaction(function () use ($data, $user) {
            $data = $this->inheritLinks($data);
            $status = $data['status'] ?? ActivityStatus::Pending->value;

            $activity = Activity::query()->create([
                ...$data,
                'status' => $status,
                'assigned_to' => $data['assigned_to'] ?? $user->id,
                'completed_at' => $status === ActivityStatus::Completed->value ? now() : null,
                'created_by' => $user->id,
            ]);

            $this->timeline->record('activity.created', $activity, sprintf('%s created: %s', $activity->type->label(), $activity->title), [
                'activity_id' => $activity->id,
                'type' => $activity->type->value,
            ]);

            return $activity;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Activity $activity, array $data, User $user): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            $wasOpen = $activity->isOpen();
            $data = $this->inheritLinks($data);

            if (isset($data['status'])) {
                $data['completed_at'] = $data['status'] === ActivityStatus::Completed->value ? ($activity->completed_at ?? now()) : null;
            }
            if (array_key_exists('due_at', $data) && $data['due_at'] !== optional($activity->due_at)->toIso8601String()) {
                $data['reminded_at'] = null;
            }

            $activity->update($data);

            if ($wasOpen && $activity->status === ActivityStatus::Completed) {
                $this->timeline->record('activity.completed', $activity, sprintf('%s completed: %s', $activity->type->label(), $activity->title), [
                    'activity_id' => $activity->id,
                ]);
            }

            return $activity;
        });
    }

    public function complete(Activity $activity, User $user): Activity
    {
        return $this->update($activity, ['status' => ActivityStatus::Completed->value], $user);
    }

    /**
     * An activity on a deal/lead is also shown on its customer's timeline.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function inheritLinks(array $data): array
    {
        if (! empty($data['deal_id']) && empty($data['customer_id'])) {
            $data['customer_id'] = Deal::query()->whereKey($data['deal_id'])->value('customer_id');
        }
        if (! empty($data['lead_id']) && empty($data['customer_id'])) {
            $data['customer_id'] = Lead::query()->whereKey($data['lead_id'])->value('customer_id');
        }

        return $data;
    }
}
