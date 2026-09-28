<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices;

use App\Enums\DeviceEventType;
use Carbon\CarbonImmutable;

/**
 * Event batch sent by an on-site reader: the hik-bridge agent (bearer token) or an office browser running the
 * Eversoft Terminal Bridge extension (user session). Both send AcsEvent entries already reduced to this shape.
 */
final class BridgeEventBatch
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'events.*.time' => ['required', 'date'],
            'events.*.employee_identifier' => ['nullable', 'string', 'max:64', 'required_without:events.*.card_number'],
            'events.*.card_number' => ['nullable', 'string', 'max:64'],
            'events.*.event_type' => ['nullable', 'in:check_in,check_out,unknown'],
            'events.*.external_id' => ['nullable', 'string', 'max:90'],
            'events.*.payload' => ['nullable', 'array'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events  validated with rules()
     * @return list<DeviceEvent>
     */
    public static function toEvents(array $events, string $timezone): array
    {
        return array_map(function (array $e) use ($timezone) {
            $time = CarbonImmutable::parse($e['time'], $timezone)->utc();

            return new DeviceEvent(
                time: $time,
                employeeIdentifier: $e['employee_identifier'] ?? null,
                cardNumber: $e['card_number'] ?? null,
                type: DeviceEventType::tryFrom($e['event_type'] ?? 'unknown') ?? DeviceEventType::Unknown,
                // Same key as the pull adapter: serial numbers wrap, so the time is included.
                externalId: isset($e['external_id']) ? 'sn:'.$e['external_id'].':'.$time->format('YmdHis') : null,
                payload: $e['payload'] ?? [],
            );
        }, array_values($events));
    }
}
