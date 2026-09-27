<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\DeviceVendor;
use App\Enums\RawEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceDeviceResource;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\AttendanceDeviceManager;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\Devices\DeviceAddressGuard;
use App\Services\Attendance\Devices\DeviceConnectionException;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Controllers never talk to device APIs directly — everything goes through the manager / sync service. */
class AttendanceDeviceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $devices = AttendanceDevice::query()
            ->withCount('identifiers')
            ->withCount(['rawEvents as unmatched_events_count' => fn ($q) => $q->where('processing_status', RawEventStatus::Unmatched->value)])
            ->orderBy('name')
            ->get();

        return $this->ok(AttendanceDeviceResource::collection($devices));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $device = new AttendanceDevice;
        $this->fill($device, $data);
        $device->save();

        return $this->created(AttendanceDeviceResource::make($device), 'Device added.');
    }

    public function show(AttendanceDevice $attendanceDevice): JsonResponse
    {
        return $this->ok(AttendanceDeviceResource::make($attendanceDevice->loadCount('identifiers')));
    }

    public function update(Request $request, AttendanceDevice $attendanceDevice): JsonResponse
    {
        $this->fill($attendanceDevice, $this->validated($request, $attendanceDevice));
        $attendanceDevice->save();

        return $this->ok(AttendanceDeviceResource::make($attendanceDevice), 'Device updated.');
    }

    public function destroy(AttendanceDevice $attendanceDevice): JsonResponse
    {
        $attendanceDevice->delete();

        return $this->ok(null, 'Device removed.');
    }

    public function test(AttendanceDevice $attendanceDevice, AttendanceDeviceManager $manager): JsonResponse
    {
        if ($attendanceDevice->connection_mode === DeviceConnectionMode::Bridge) {
            return $this->ok([
                'success' => $attendanceDevice->last_sync_at !== null && $attendanceDevice->last_sync_at->greaterThan(now()->subMinutes(15)),
                'message' => $attendanceDevice->last_sync_at
                    ? 'Bridge device. Last event batch received '.$attendanceDevice->last_sync_at->diffForHumans().'.'
                    : 'Bridge device. No data received from the bridge agent yet.',
            ]);
        }

        $result = $manager->for($attendanceDevice)->testConnection();

        if ($result->success && ! empty($result->info['serial_number']) && ! $attendanceDevice->serial_number) {
            $attendanceDevice->forceFill(['serial_number' => $result->info['serial_number'], 'model' => $attendanceDevice->model ?: ($result->info['model'] ?? null)])->save();
        }

        $this->audit->log('device.tested', $attendanceDevice, [], ['success' => $result->success, 'message' => $result->message]);

        return $this->ok($result->toArray(), $result->message);
    }

    public function sync(Request $request, AttendanceDevice $attendanceDevice, AttendanceSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : null;
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : null;
        abort_if($from && ($to ?? CarbonImmutable::now())->diffInDays($from, true) > 31, 422, 'Sync at most 31 days at a time.');

        $result = $sync->sync($attendanceDevice, $from, $to);
        $this->audit->log('device.synced', $attendanceDevice, [], ['success' => $result['success']]);

        return $this->ok([...$result, 'device' => AttendanceDeviceResource::make($attendanceDevice->refresh())->resolve()], $result['message'] ?? null);
    }

    /** Push mapped employees to the terminal (create/update users, remove inactive). */
    public function pushEmployees(AttendanceDevice $attendanceDevice, AttendanceDeviceManager $manager): JsonResponse
    {
        abort_if($attendanceDevice->connection_mode === DeviceConnectionMode::Bridge, 422, 'Bridge devices are managed on-site.');

        try {
            $manager->for($attendanceDevice)->syncEmployees();
        } catch (DeviceConnectionException $e) {
            Log::channel('attendance')->warning('Employee push failed', ['device_id' => $attendanceDevice->id, 'error' => $e->getMessage()]);

            return $this->error('Could not update the device: '.$e->getMessage(), 502);
        }

        $this->audit->log('device.employees_pushed', $attendanceDevice);

        return $this->ok(null, 'Employees pushed to device.');
    }

    /** Issue a new bridge token (shown once; only its hash is stored). */
    public function bridgeToken(AttendanceDevice $attendanceDevice): JsonResponse
    {
        $token = 'hbr_'.Str::random(48);
        $attendanceDevice->forceFill(['bridge_token_hash' => hash('sha256', $token)])->save();
        $this->audit->log('device.bridge_token_issued', $attendanceDevice);

        return $this->ok(['token' => $token, 'device_id' => $attendanceDevice->id], 'Copy this token now; it will not be shown again.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AttendanceDevice $device = null): array
    {
        $direct = $request->input('connection_mode', $device?->connection_mode?->value ?? 'direct') === 'direct';
        $isHikvision = $request->input('vendor', $device?->vendor?->value) === 'hikvision';

        return $request->validate([
            'name' => [$device ? 'sometimes' : 'required', 'string', 'max:255'],
            'vendor' => [$device ? 'sometimes' : 'required', Rule::enum(DeviceVendor::class)],
            'model' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:64'],
            'type' => ['sometimes', Rule::enum(DeviceType::class)],
            'connection_mode' => ['sometimes', Rule::enum(DeviceConnectionMode::class)],
            // Host name or IP address of the terminal.
            'ip_address' => [$direct && $isHikvision && ! $device ? 'required' : 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && $value !== '' && ($problem = DeviceAddressGuard::problem($value))) {
                        $fail($problem);
                    }
                }],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'protocol' => ['sometimes', 'in:http,https'],
            'username' => ['nullable', 'string', 'max:100'],
            // Write-only. Omit to keep the stored password.
            'password' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(DeviceStatus::class)],
            'settings' => ['sometimes', 'array'],
            'settings.timezone' => ['nullable', 'timezone:all'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fill(AttendanceDevice $device, array $data): void
    {
        if (array_key_exists('password', $data)) {
            if ($data['password'] !== null && $data['password'] !== '') {
                $device->encrypted_password = $data['password'];
            }
            unset($data['password']);
        }
        if (isset($data['settings'])) {
            $data['settings'] = [...(array) $device->settings, ...array_intersect_key($data['settings'], ['timezone' => true])];
        }

        $device->fill($data);
    }
}
