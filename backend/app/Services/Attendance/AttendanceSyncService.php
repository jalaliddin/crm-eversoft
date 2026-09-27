<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceStatus;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceConnectionException;
use App\Services\Attendance\Devices\DeviceEvent;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Device → fetch → store raw → deduplicate → resolve employee → process attendance.
 *
 * Safe to run repeatedly (scheduler + manual "Sync now"): a cache lock prevents overlapping runs per device,
 * raw events are deduplicated by the database, and records are recomputed idempotently.
 * An unreachable device never throws out of sync(); the failure is recorded on the device.
 */
class AttendanceSyncService
{
    public function __construct(
        private readonly AttendanceDeviceManager $devices,
        private readonly RawEventIngestor $ingestor,
        private readonly RawEventProcessor $processor,
        private readonly TenantContext $tenant,
    ) {}

    /** @return array<string, mixed> */
    public function sync(AttendanceDevice $device, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $lock = Cache::lock('attendance-sync:device:'.$device->id, 600);

        if (! $lock->get()) {
            return ['success' => false, 'message' => 'A synchronization for this device is already running.'];
        }

        try {
            return $this->tenant->run($device->organization, fn () => $this->run($device, $from, $to));
        } finally {
            $lock->release();
        }
    }

    /**
     * Ingest events pushed by an on-site bridge agent (device in bridge mode).
     *
     * @param  iterable<DeviceEvent>  $events
     * @return array<string, mixed>
     */
    public function ingestPushed(AttendanceDevice $device, iterable $events): array
    {
        return $this->tenant->run($device->organization, function () use ($device, $events) {
            $stored = $this->ingestor->store($device, $events);
            $processed = $this->processor->process($device->organization_id, $device->id);
            $this->markSuccess($device, $stored['latest']);

            return ['success' => true, ...$this->summary($stored, $processed)];
        });
    }

    /** @return array<string, mixed> */
    private function run(AttendanceDevice $device, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $context = ['device_id' => $device->id, 'organization_id' => $device->organization_id];

        if ($device->status !== DeviceStatus::Active) {
            return ['success' => false, 'message' => 'Device is inactive.'];
        }

        if ($device->connection_mode === DeviceConnectionMode::Bridge) {
            // Events arrive through the bridge; only (re)process what is pending.
            $processed = $this->processor->process($device->organization_id, $device->id);

            return ['success' => true, 'message' => 'Bridge device: processed pending events.', ...$processed];
        }

        $to ??= CarbonImmutable::now();
        $from ??= $device->last_event_at
            ? CarbonImmutable::instance($device->last_event_at)->subMinutes((int) config('attendance.sync.overlap_minutes'))
            : $to->subDays((int) config('attendance.sync.initial_lookback_days'));

        $totals = ['received' => 0, 'stored' => 0, 'duplicates' => 0, 'latest' => null];

        try {
            $adapter = $this->devices->for($device);
            $windowHours = max(1, (int) config('attendance.sync.window_hours'));

            for ($start = $from; $start->lessThan($to); $start = $end) {
                $end = $start->addHours($windowHours)->min($to);
                $result = $this->ingestor->store($device, $adapter->getEvents(Carbon::instance($start), Carbon::instance($end)));

                $totals['received'] += $result['received'];
                $totals['stored'] += $result['stored'];
                $totals['duplicates'] += $result['duplicates'];
                if ($result['latest'] && ($totals['latest'] === null || $result['latest']->greaterThan($totals['latest']))) {
                    $totals['latest'] = $result['latest'];
                }
            }
        } catch (DeviceConnectionException $e) {
            $device->forceFill(['last_sync_at' => now(), 'last_sync_status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            Log::channel('attendance')->warning('Device sync failed', [...$context, 'error' => $e->getMessage()]);

            // Events already stored before the failure are still processed.
            $processed = $this->processor->process($device->organization_id, $device->id);

            return ['success' => false, 'message' => $e->getMessage(), ...$this->summary($totals, $processed)];
        } catch (Throwable $e) {
            $device->forceFill(['last_sync_at' => now(), 'last_sync_status' => 'failed', 'last_error' => 'Unexpected error: '.mb_substr($e->getMessage(), 0, 900)])->save();
            Log::channel('attendance')->error('Device sync crashed', [...$context, 'error' => $e->getMessage()]);
            report($e);

            return ['success' => false, 'message' => 'Synchronization failed unexpectedly.'];
        }

        $processed = $this->processor->process($device->organization_id, $device->id);
        $this->markSuccess($device, $totals['latest']);

        $summary = $this->summary($totals, $processed);
        Log::channel('attendance')->info('Device synced', [...$context, ...$summary, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String()]);

        return ['success' => true, 'message' => 'Synchronized.', ...$summary];
    }

    private function markSuccess(AttendanceDevice $device, ?CarbonImmutable $latest): void
    {
        $device->forceFill([
            'last_sync_at' => now(),
            'last_sync_status' => 'ok',
            'last_error' => null,
            'last_event_at' => $latest && ($device->last_event_at === null || $latest->greaterThan($device->last_event_at))
                ? $latest
                : $device->last_event_at,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, int>  $processed
     * @return array<string, int>
     */
    private function summary(array $stored, array $processed): array
    {
        return [
            'received' => $stored['received'],
            'stored' => $stored['stored'],
            'duplicates' => $stored['duplicates'],
            'processed' => $processed['processed'],
            'unmatched' => $processed['unmatched'],
            'failed' => $processed['failed'],
            'records_updated' => $processed['records'],
        ];
    }
}
