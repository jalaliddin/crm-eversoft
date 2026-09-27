<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Enums\OrganizationStatus;
use App\Jobs\SyncAttendanceDeviceJob;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AttendanceSyncCommand extends Command
{
    protected $signature = 'attendance:sync
        {--device= : Only this device id}
        {--organization= : Only devices of this organization id}
        {--from= : Start of the window (ISO-8601), default: since the last seen event}
        {--to= : End of the window (ISO-8601), default: now}
        {--queue : Dispatch one queued job per device instead of running inline}';

    protected $description = 'Fetch attendance events from biometric devices and process them';

    public function handle(AttendanceSyncService $sync): int
    {
        $devices = AttendanceDevice::withoutGlobalScopes()
            ->with('organization')
            ->where('status', DeviceStatus::Active->value)
            ->whereHas('organization', fn ($q) => $q->whereIn('status', [OrganizationStatus::Active->value, OrganizationStatus::Trial->value]))
            ->when($this->option('device'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('organization'), fn ($q, $id) => $q->where('organization_id', $id))
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No active devices to synchronize.');

            return self::SUCCESS;
        }

        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : null;
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : null;
        $failures = 0;

        foreach ($devices as $device) {
            if ($this->option('queue')) {
                SyncAttendanceDeviceJob::dispatch($device->id);
                $this->line("Queued device #{$device->id} {$device->name}");

                continue;
            }

            $result = $sync->sync($device, $from, $to);
            $failures += $result['success'] ? 0 : 1;
            $this->line(sprintf(
                '%s #%d %s — %s (received %d, new %d, duplicates %d, unmatched %d)',
                $result['success'] ? '<info>OK</info>' : '<error>FAIL</error>',
                $device->id,
                $device->name,
                $result['message'] ?? '',
                $result['received'] ?? 0,
                $result['stored'] ?? 0,
                $result['duplicates'] ?? 0,
                $result['unmatched'] ?? 0,
            ));
        }

        // A failing device is reported, not fatal: other devices were still synchronized.
        return $failures === $devices->count() ? self::FAILURE : self::SUCCESS;
    }
}
