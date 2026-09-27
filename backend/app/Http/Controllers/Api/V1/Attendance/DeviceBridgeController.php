<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceEventType;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\Devices\DeviceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Ingest endpoint for the on-site bridge agent (hik-bridge). Authenticated with a per-device token
 * (Authorization: Bearer hbr_...), not a user session. The device — and therefore the tenant — is derived
 * from the token alone.
 */
class DeviceBridgeController extends Controller
{
    public function events(Request $request, AttendanceSyncService $sync): JsonResponse
    {
        $token = (string) $request->bearerToken();
        $device = $token !== '' && str_starts_with($token, 'hbr_')
            ? AttendanceDevice::withoutGlobalScopes()->with('organization')->where('bridge_token_hash', hash('sha256', $token))->first()
            : null;

        if ($device === null || $device->connection_mode !== DeviceConnectionMode::Bridge) {
            Log::channel('security')->warning('Rejected bridge token', ['ip' => $request->ip()]);

            return $this->error('Invalid device token.', 401);
        }
        if ($device->status !== DeviceStatus::Active || ! $device->organization?->isActive()) {
            return $this->error('Device or organization is inactive.', 403);
        }

        $data = $request->validate([
            'events' => ['required', 'array', 'max:1000'],
            'events.*.time' => ['required', 'date'],
            'events.*.employee_identifier' => ['nullable', 'string', 'max:64', 'required_without:events.*.card_number'],
            'events.*.card_number' => ['nullable', 'string', 'max:64'],
            'events.*.event_type' => ['nullable', 'in:check_in,check_out,unknown'],
            'events.*.external_id' => ['nullable', 'string', 'max:90'],
            'events.*.payload' => ['nullable', 'array'],
        ]);

        $timezone = $device->settings['timezone'] ?? $device->organization->timezone;
        $events = array_map(fn (array $e) => new DeviceEvent(
            time: CarbonImmutable::parse($e['time'], $timezone)->utc(),
            employeeIdentifier: $e['employee_identifier'] ?? null,
            cardNumber: $e['card_number'] ?? null,
            type: DeviceEventType::tryFrom($e['event_type'] ?? 'unknown') ?? DeviceEventType::Unknown,
            externalId: isset($e['external_id']) ? 'sn:'.$e['external_id'] : null,
            payload: $e['payload'] ?? [],
        ), $data['events']);

        return $this->ok($sync->ingestPushed($device, $events));
    }
}
