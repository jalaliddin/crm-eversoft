<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RawEventStatus;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Organization;
use App\Services\Attendance\RawEventProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class AttendanceProcessPendingCommand extends Command
{
    protected $signature = 'attendance:process-pending {--unmatched : Also retry unmatched events}';

    protected $description = 'Process raw attendance events that are still pending (and optionally unmatched)';

    public function handle(RawEventProcessor $processor, TenantContext $tenant): int
    {
        $statuses = [RawEventStatus::Pending->value, ...($this->option('unmatched') ? [RawEventStatus::Unmatched->value] : [])];

        $organizationIds = AttendanceRawEvent::withoutGlobalScopes()->whereIn('processing_status', $statuses)->distinct()->pluck('organization_id');

        foreach (Organization::query()->whereIn('id', $organizationIds)->get() as $organization) {
            $stats = $tenant->run($organization, fn () => $processor->process($organization->id, null, $statuses));
            $this->line(sprintf('%s: processed %d, unmatched %d, failed %d', $organization->name, $stats['processed'], $stats['unmatched'], $stats['failed']));
        }

        return self::SUCCESS;
    }
}
