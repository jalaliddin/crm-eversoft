<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceDevicePushLog;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\Devices\Hikvision\HikvisionPushParser;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives ISAPI event notifications a terminal posts to its listening host (HTTP push mode).
 *
 * The terminal cannot send our bearer token, so the device is identified by a secret token in the URL path
 * (configured as the listening host "url"). Only its SHA-256 is stored. Every request is recorded in the
 * device's push log — without picture data — so the real payload can be inspected from the UI.
 */
class HikvisionPushController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        HikvisionPushParser $parser,
        AttendanceSyncService $sync,
        TenantContext $tenant,
    ): JsonResponse {
        $device = str_starts_with($token, 'hpt_')
            ? AttendanceDevice::withoutGlobalScopes()->with('organization')->where('bridge_token_hash', hash('sha256', $token))->first()
            : null;

        if ($device === null || $device->connection_mode !== DeviceConnectionMode::Push) {
            Log::channel('security')->warning('Rejected Hikvision push: unknown device token', ['ip' => $request->ip()]);

            return $this->error('Unknown device.', 404);
        }

        return $tenant->run($device->organization, function () use ($request, $device, $parser, $sync) {
            $log = new AttendanceDevicePushLog([
                'device_id' => $device->id,
                'content_type' => mb_substr((string) $request->header('Content-Type'), 0, 191),
                'remote_ip' => $request->ip(),
            ]);

            if ($device->status !== DeviceStatus::Active || ! $device->organization?->isActive()) {
                $log->fill(['status' => 'rejected', 'error' => 'Device or organization is inactive.'])->save();

                return $this->error('Device is inactive.', 403);
            }

            try {
                $timezone = $device->settings['timezone'] ?? $device->organization->timezone;
                $parsed = $parser->parse($request, $timezone);
                $log->fill([
                    'status' => $parsed->kind,
                    'event_type' => $parsed->eventType ? mb_substr($parsed->eventType, 0, 64) : null,
                    'events_count' => count($parsed->events),
                    'summary' => $parsed->summary ? mb_substr($parsed->summary, 0, 255) : null,
                    'body_excerpt' => $parsed->excerpt,
                ]);

                if ($parsed->kind === 'unparsed') {
                    Log::channel('hikvision')->notice('Unrecognized push payload', ['device_id' => $device->id, 'content_type' => $log->content_type]);
                }

                if ($parsed->events !== []) {
                    $result = $sync->ingestPushed($device, $parsed->events);
                    $log->stored_count = (int) ($result['stored'] ?? 0);
                } elseif ($parsed->kind !== 'unparsed') {
                    $sync->markSeen($device);
                }

                $log->save();
            } catch (Throwable $e) {
                $log->fill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();
                report($e);

                // A non-2xx answer lets the terminal retry; duplicates are rejected, so retries are safe.
                return $this->error('Processing failed.', 500);
            }

            return $this->ok(null, 'OK');
        });
    }
}
