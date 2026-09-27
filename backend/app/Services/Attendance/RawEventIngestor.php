<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\RawEventStatus;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stores raw punches exactly once. Deduplication is enforced by the database unique key
 * (device_id, external_event_id), so concurrent or repeated syncs can never double-insert.
 */
class RawEventIngestor
{
    /**
     * @param  iterable<DeviceEvent>  $events
     * @return array{received: int, stored: int, duplicates: int, latest: ?CarbonImmutable}
     */
    public function store(AttendanceDevice $device, iterable $events): array
    {
        $received = 0;
        $stored = 0;
        $latest = null;
        $batch = [];
        $now = now();
        $size = (int) config('attendance.sync.batch_size', 500);

        $flush = function () use (&$batch, &$stored): void {
            if ($batch !== []) {
                $stored += DB::table('attendance_raw_events')->insertOrIgnore($batch);
                $batch = [];
            }
        };

        foreach ($events as $event) {
            $received++;
            $latest = $latest === null || $event->time->greaterThan($latest) ? $event->time : $latest;
            $key = $event->dedupKey($device->id);

            // Also dedupe inside the batch (same key twice in one page).
            $batch[$key] = [
                'organization_id' => $device->organization_id,
                'device_id' => $device->id,
                'external_event_id' => $key,
                'employee_identifier' => $event->employeeIdentifier,
                'card_number' => $event->cardNumber,
                'event_type' => $event->type->value,
                'event_time' => $event->time->utc()->format('Y-m-d H:i:s'),
                'payload' => json_encode($event->payload, JSON_UNESCAPED_UNICODE),
                'processing_status' => RawEventStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $size) {
                $batch = array_values($batch);
                $flush();
            }
        }

        $batch = array_values($batch);
        $flush();

        return ['received' => $received, 'stored' => $stored, 'duplicates' => $received - $stored, 'latest' => $latest];
    }
}
