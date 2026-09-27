<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Enums\EmployeeStatus;
use App\Enums\RecordStatus;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Hr\Employee;
use App\Services\Attendance\Devices\AttendanceDeviceInterface;
use App\Services\Attendance\Devices\DeviceConnectionException;
use App\Services\Attendance\Devices\DeviceConnectionResult;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Hikvision access-control terminal (primary target: DS-K1A340FWX) via ISAPI. */
class HikvisionAttendanceDevice implements AttendanceDeviceInterface
{
    public function __construct(
        private readonly AttendanceDevice $device,
        private readonly HikvisionClient $client,
        private readonly HikvisionEventMapper $mapper,
    ) {}

    public function testConnection(): DeviceConnectionResult
    {
        $started = microtime(true);

        try {
            $info = $this->client->getXml('/ISAPI/System/deviceInfo');
        } catch (DeviceConnectionException $e) {
            return new DeviceConnectionResult(false, $e->getMessage());
        }

        return new DeviceConnectionResult(
            success: true,
            message: 'Connected.',
            latencyMs: (int) ((microtime(true) - $started) * 1000),
            info: array_filter([
                'device_name' => $info['deviceName'] ?? null,
                'model' => $info['model'] ?? null,
                'serial_number' => $info['serialNumber'] ?? null,
                'firmware_version' => $info['firmwareVersion'] ?? null,
                'mac_address' => $info['macAddress'] ?? null,
            ], fn ($v) => is_string($v) && $v !== ''),
        );
    }

    public function getEvents(Carbon $from, Carbon $to): iterable
    {
        $timezone = $this->timezone();
        $position = 0;
        $pageSize = (int) config('attendance.hikvision.page_size');
        $searchId = (string) Str::uuid();

        do {
            $response = $this->client->postJson('/ISAPI/AccessControl/AcsEvent?format=json', [
                'AcsEventCond' => [
                    'searchID' => $searchId,
                    'searchResultPosition' => $position,
                    'maxResults' => $pageSize,
                    'major' => (int) config('attendance.hikvision.event_major'),
                    'minor' => 0,
                    'startTime' => CarbonImmutable::instance($from)->setTimezone($timezone)->format('Y-m-d\TH:i:sP'),
                    'endTime' => CarbonImmutable::instance($to)->setTimezone($timezone)->format('Y-m-d\TH:i:sP'),
                ],
            ]);

            $items = $this->mapper->infoList($response);

            foreach ($items as $info) {
                $event = $this->mapper->map($info, $timezone);
                if ($event !== null) {
                    yield $event;
                }
            }

            $position += count($items);
            // Guard against firmware that keeps answering MORE with an empty page.
            $more = $this->mapper->hasMore($response) && $items !== [];
        } while ($more);
    }

    public function syncEmployees(): void
    {
        $identifiers = $this->identifiers()->with('employee')->get();

        foreach ($identifiers as $identifier) {
            $employee = $identifier->employee;
            if ($employee === null) {
                continue;
            }

            $active = $identifier->status === RecordStatus::Active
                && in_array($employee->status, [EmployeeStatus::Active, EmployeeStatus::OnLeave], true);

            $active ? $this->upsertUser($employee, $identifier) : $this->deleteUser($identifier->identifier);
        }
    }

    public function pushEmployee(Employee $employee): void
    {
        $identifier = $this->identifierFor($employee);
        if ($identifier === null) {
            throw new DeviceConnectionException('Employee has no device identifier for this device.');
        }

        $this->upsertUser($employee, $identifier);
    }

    public function removeEmployee(Employee $employee): void
    {
        $identifier = $this->identifierFor($employee);
        if ($identifier !== null) {
            $this->deleteUser($identifier->identifier);
        }
    }

    private function upsertUser(Employee $employee, EmployeeDeviceIdentifier $identifier): void
    {
        $userInfo = [
            'employeeNo' => $identifier->identifier,
            'name' => mb_substr(trim($employee->last_name.' '.$employee->first_name), 0, 32),
            'userType' => 'normal',
            'Valid' => [
                'enable' => true,
                'beginTime' => '2000-01-01T00:00:00',
                'endTime' => '2037-12-31T23:59:59',
                'timeType' => 'local',
            ],
        ];

        try {
            $this->client->postJson('/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => $userInfo]);
        } catch (HikvisionRequestException $e) {
            if ($e->subStatusCode !== 'employeeNoAlreadyExist') {
                throw $e;
            }
            // Keep enrolled face/fingerprint data: modify in place instead of delete + recreate.
            $this->client->putJson('/ISAPI/AccessControl/UserInfo/Modify?format=json', ['UserInfo' => $userInfo]);
        }

        if ($identifier->card_number) {
            Log::channel('hikvision')->info('Card numbers are enrolled on the device; not pushed automatically.', [
                'device_id' => $this->device->id,
                'employee_id' => $employee->id,
            ]);
        }
    }

    private function deleteUser(string $employeeNo): void
    {
        $this->client->putJson('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
            'UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => $employeeNo]]],
        ]);
    }

    private function identifiers()
    {
        return EmployeeDeviceIdentifier::withoutGlobalScopes()
            ->where('organization_id', $this->device->organization_id)
            ->where(fn ($q) => $q->where('device_id', $this->device->id)->orWhereNull('device_id'));
    }

    private function identifierFor(Employee $employee): ?EmployeeDeviceIdentifier
    {
        return $this->identifiers()
            ->where('employee_id', $employee->id)
            ->orderByRaw('device_id is null')
            ->first();
    }

    private function timezone(): string
    {
        return $this->device->settings['timezone'] ?? $this->device->organization?->timezone ?? config('app.default_tenant_timezone');
    }
}
