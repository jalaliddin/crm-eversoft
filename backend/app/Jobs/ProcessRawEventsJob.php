<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Attendance\RawEventProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** (Re)process pending or unmatched raw events for an organization, e.g. after new identifier mappings. */
class ProcessRawEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    /** @param list<string> $statuses */
    public function __construct(public readonly int $organizationId, public readonly array $statuses = ['pending', 'unmatched']) {}

    public function handle(RawEventProcessor $processor, TenantContext $tenant): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization !== null) {
            $tenant->run($organization, fn () => $processor->process($organization->id, null, $this->statuses));
        }
    }
}
