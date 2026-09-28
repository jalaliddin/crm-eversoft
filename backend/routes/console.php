<?php

use App\Models\Attendance\AttendanceDevicePushLog;
use App\Services\System\HealthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Pull events from directly reachable devices. One queued job per device keeps slow devices isolated.
Schedule::command('attendance:sync --queue')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Finalize yesterday (absences / incomplete). Runs hourly; each organization closes its own "yesterday",
// and closing is idempotent, so tenants in any timezone are handled.
Schedule::command('attendance:close-day')->hourlyAt(15)->withoutOverlapping()->onOneServer();

// Reprocess leftovers (e.g. events that arrived before an identifier mapping existed).
Schedule::command('attendance:process-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('activities:remind-due')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('model:prune', ['--model' => [AttendanceDevicePushLog::class]])->daily();

// Heartbeat read by the health page.
Schedule::call(fn () => Cache::put(HealthService::HEARTBEAT_KEY, now()->toIso8601ZuluString(), 600))->everyMinute()->name('scheduler-heartbeat');
