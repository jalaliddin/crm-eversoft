<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Services\Attendance\Devices\DeviceEvent;

final readonly class PushParseResult
{
    /**
     * @param  string  $kind  accepted | heartbeat | ignored | unparsed
     * @param  list<DeviceEvent>  $events
     */
    public function __construct(
        public string $kind,
        public ?string $eventType,
        public array $events,
        public ?string $summary,
        public string $excerpt,
    ) {}
}
