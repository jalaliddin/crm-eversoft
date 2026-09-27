<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SyncAttendanceDeviceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly int $deviceId) {}

    public function uniqueId(): string
    {
        return (string) $this->deviceId;
    }

    public function handle(AttendanceSyncService $sync): void
    {
        $device = AttendanceDevice::withoutGlobalScopes()->with('organization')->find($this->deviceId);

        if ($device !== null && $device->organization?->isActive()) {
            $sync->sync($device);
        }
    }
}
