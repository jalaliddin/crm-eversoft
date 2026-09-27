<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\Attendance\AttendanceDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthService
{
    public const HEARTBEAT_KEY = 'system:scheduler:heartbeat';

    /** @return array<string, mixed> */
    public function report(bool $includeDevices = true): array
    {
        $checks = [
            'application' => [
                'status' => 'ok',
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => config('app.timezone'),
            ],
            'database' => $this->check(function () {
                $started = microtime(true);
                DB::select('select 1');

                return ['latency_ms' => (int) ((microtime(true) - $started) * 1000), 'driver' => DB::getDriverName()];
            }),
            'cache' => $this->check(function () {
                $key = 'health:'.uniqid();
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return ['store' => config('cache.default'), 'status' => $ok ? 'ok' : 'error'];
            }),
            'queue' => $this->check(fn () => [
                'connection' => config('queue.default'),
                'pending' => config('queue.default') === 'sync' ? 0 : Queue::size(),
                'failed' => DB::table('failed_jobs')->count(),
            ]),
            'scheduler' => $this->scheduler(),
        ];

        if ($includeDevices) {
            $devices = AttendanceDevice::query()->orderBy('name')->get();
            $checks['attendance_devices'] = [
                'status' => $devices->contains(fn ($d) => $d->last_sync_status === 'failed') ? 'warning' : 'ok',
                'total' => $devices->count(),
                'last_sync_at' => $devices->max('last_sync_at')?->toIso8601ZuluString(),
                'devices' => $devices->map(fn (AttendanceDevice $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status->value,
                    'last_sync_at' => $d->last_sync_at?->toIso8601ZuluString(),
                    'last_sync_status' => $d->last_sync_status,
                    'last_error' => $d->last_error,
                ])->values(),
            ];
        }

        $checks['status'] = collect($checks)->contains(fn ($c) => is_array($c) && ($c['status'] ?? 'ok') === 'error') ? 'error' : 'ok';

        return $checks;
    }

    /** @return array<string, mixed> */
    private function scheduler(): array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        return [
            'status' => $last && now()->diffInSeconds($last, true) < 180 ? 'ok' : 'warning',
            'last_run_at' => $last,
        ];
    }

    /** @return array<string, mixed> */
    private function check(callable $probe): array
    {
        try {
            return ['status' => 'ok', ...$probe()];
        } catch (Throwable $e) {
            return ['status' => 'error', 'error' => config('app.debug') ? $e->getMessage() : 'Unavailable'];
        }
    }
}
