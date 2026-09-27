<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\TimelineEvent;
use App\Models\Document;
use App\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Reusable CRM timeline. Each event is linked to every customer / lead / deal it concerns,
 * so a deal stage change shows on the deal, its lead and its customer.
 */
class TimelineRecorder
{
    /** @param array<string, mixed> $properties */
    public function record(string $event, Model $subject, string $description, array $properties = []): TimelineEvent
    {
        [$customerId, $leadId, $dealId] = $this->links($subject);

        return TimelineEvent::query()->create([
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'customer_id' => $customerId,
            'lead_id' => $leadId,
            'deal_id' => $dealId,
            'description' => mb_substr($description, 0, 255),
            'properties' => $properties ?: null,
            'causer_id' => Auth::id(),
            'occurred_at' => now(),
        ]);
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} */
    private function links(Model $subject): array
    {
        return match (true) {
            $subject instanceof Customer => [$subject->id, null, null],
            $subject instanceof Lead => [$subject->customer_id, $subject->id, null],
            $subject instanceof Deal => [$subject->customer_id, $subject->lead_id, $subject->id],
            $subject instanceof Activity => [$subject->customer_id, $subject->lead_id, $subject->deal_id],
            $subject instanceof Note => $this->links($subject->notable),
            $subject instanceof Document => $this->links($subject->documentable),
            default => [null, null, null],
        };
    }
}
