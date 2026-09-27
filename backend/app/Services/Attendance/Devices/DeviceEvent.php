<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices;

use App\Enums\DeviceEventType;
use Carbon\CarbonImmutable;

/** Normalized punch coming from any device vendor. */
final readonly class DeviceEvent
{
    /** @param array<string, mixed> $payload raw vendor payload, kept for audit / reprocessing */
    public function __construct(
        public CarbonImmutable $time,
        public ?string $employeeIdentifier,
        public ?string $cardNumber = null,
        public DeviceEventType $type = DeviceEventType::Unknown,
        public ?string $externalId = null,
        public array $payload = [],
    ) {}

    /** Device id when supplied, otherwise a deterministic hash so re-imports deduplicate. */
    public function dedupKey(int $deviceId): string
    {
        if ($this->externalId !== null && $this->externalId !== '') {
            return mb_substr($this->externalId, 0, 100);
        }

        return 'h:'.sha1(implode('|', [
            $deviceId,
            $this->employeeIdentifier ?? '',
            $this->cardNumber ?? '',
            $this->time->utc()->format('Y-m-d\TH:i:s'),
            $this->type->value,
        ]));
    }
}
