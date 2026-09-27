<?php

return [
    // SSRF protection for device addresses (see DeviceAddressGuard).
    'allow_private_networks' => (bool) env('ATTENDANCE_ALLOW_PRIVATE_NETWORKS', true),
    'blocked_ranges' => env('ATTENDANCE_BLOCKED_RANGES', '127.0.0.0/8,0.0.0.0/8,169.254.0.0/16,100.64.0.0/10,::1/128,fe80::/10,224.0.0.0/4'),

    'sync' => [
        // First sync of a new device looks back this many days.
        'initial_lookback_days' => (int) env('ATTENDANCE_INITIAL_LOOKBACK_DAYS', 2),
        // Re-read this many minutes before the last seen event to catch late-arriving device buffers.
        'overlap_minutes' => (int) env('ATTENDANCE_SYNC_OVERLAP_MINUTES', 10),
        // Max span of one device query; longer ranges are split.
        'window_hours' => (int) env('ATTENDANCE_SYNC_WINDOW_HOURS', 24),
        'batch_size' => 500,
        // Devices with more pending events than this are processed on the queue.
        'queue_threshold' => 2000,
    ],

    'hikvision' => [
        'timeout' => (int) env('HIKVISION_TIMEOUT', 15),
        'connect_timeout' => (int) env('HIKVISION_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('HIKVISION_RETRIES', 2),
        'retry_sleep_ms' => (int) env('HIKVISION_RETRY_SLEEP_MS', 500),
        // AcsEvent search page size; the device answers responseStatusStrg=MORE when more pages exist.
        'page_size' => (int) env('HIKVISION_PAGE_SIZE', 30),
        // AcsEvent "major" class 5 = access-control events (authentication results).
        'event_major' => 5,
        'verify_tls' => (bool) env('HIKVISION_VERIFY_TLS', false),
    ],
];
