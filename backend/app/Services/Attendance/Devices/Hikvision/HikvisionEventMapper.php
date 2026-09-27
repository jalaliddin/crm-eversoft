<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Enums\DeviceEventType;
use App\Services\Attendance\Devices\DeviceEvent;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Maps AcsEvent InfoList entries to DeviceEvent.
 *
 * Fields relied upon: time (ISO-8601, usually with offset), employeeNoString, cardNo,
 * serialNo (per-device event sequence), attendanceStatus (checkIn/checkOut/breakIn/breakOut/overtimeIn/overtimeOut/undefined),
 * major / minor. Entries without a person or card (door alarms, tamper, etc.) are skipped.
 */
class HikvisionEventMapper
{
    /**
     * @param  array<string, mixed>  $info
     * @param  string  $timezone  used only when the device omits the UTC offset
     */
    public function map(array $info, string $timezone): ?DeviceEvent
    {
        $identifier = trim((string) ($info['employeeNoString'] ?? $info['employeeNo'] ?? ''));
        $card = trim((string) ($info['cardNo'] ?? ''));

        if (($identifier === '' && $card === '') || empty($info['time'])) {
            return null;
        }

        try {
            $time = CarbonImmutable::parse((string) $info['time'], $timezone)->utc();
        } catch (Throwable) {
            return null;
        }

        return new DeviceEvent(
            time: $time,
            employeeIdentifier: $identifier !== '' ? $identifier : null,
            cardNumber: $card !== '' ? $card : null,
            type: $this->type((string) ($info['attendanceStatus'] ?? '')),
            externalId: isset($info['serialNo']) && $info['serialNo'] !== '' ? 'sn:'.$info['serialNo'] : null,
            payload: array_intersect_key($info, array_flip([
                'major', 'minor', 'time', 'employeeNoString', 'cardNo', 'name', 'serialNo', 'attendanceStatus',
                'currentVerifyMode', 'doorNo', 'cardReaderNo', 'userType', 'mask',
            ])),
        );
    }

    /** @param array<string, mixed> $response @return list<array<string, mixed>> */
    public function infoList(array $response): array
    {
        $list = $response['AcsEvent']['InfoList'] ?? [];

        if ($list === [] || ! is_array($list)) {
            return [];
        }

        // Some firmwares return a single object instead of a list.
        return isset($list['time']) || isset($list['major']) ? [$list] : array_values($list);
    }

    /** @param array<string, mixed> $response */
    public function hasMore(array $response): bool
    {
        return strtoupper((string) ($response['AcsEvent']['responseStatusStrg'] ?? 'OK')) === 'MORE';
    }

    private function type(string $attendanceStatus): DeviceEventType
    {
        return match (strtolower($attendanceStatus)) {
            'checkin', 'breakin', 'overtimein' => DeviceEventType::CheckIn,
            'checkout', 'breakout', 'overtimeout' => DeviceEventType::CheckOut,
            default => DeviceEventType::Unknown,
        };
    }
}
