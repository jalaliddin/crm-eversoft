<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices;

final readonly class DeviceConnectionResult
{
    /** @param array<string, mixed> $info */
    public function __construct(
        public bool $success,
        public string $message,
        public ?int $latencyMs = null,
        public array $info = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['success' => $this->success, 'message' => $this->message, 'latency_ms' => $this->latencyMs, 'info' => $this->info];
    }
}
