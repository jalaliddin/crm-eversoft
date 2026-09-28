<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Services\Attendance\Devices\DeviceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use Throwable;

/**
 * Parses event notifications a terminal pushes to its "listening host" (PUT /ISAPI/Event/notification/httpHosts).
 *
 * Per the ISAPI Developer Guide (Face Recognition Terminals), the device sends multipart/form-data whose parts are
 * an EventNotificationAlert (JSON or XML, per parameterFormatType) and optionally pictures. The alert carries
 * dateTime and eventType; heartbeats have eventType "heartBeat"; access events have eventType
 * "AccessControllerEvent" plus an AccessControllerEvent object (employeeNoString, cardNo, serialNo, attendanceStatus, …).
 *
 * The parser is deliberately tolerant (raw JSON/XML bodies, single alerts or lists) because firmwares differ.
 * Picture parts are ignored: the CRM never stores biometric images.
 */
class HikvisionPushParser
{
    private const MAX_TEXT_PART = 262144; // 256 KB

    public function __construct(private readonly HikvisionEventMapper $mapper) {}

    public function parse(Request $request, string $timezone): PushParseResult
    {
        $texts = $this->textParts($request);
        $alerts = [];

        foreach ($texts as $text) {
            foreach ($this->alertsIn($this->decode($text)) as $alert) {
                $alerts[] = $alert;
            }
        }

        $excerpt = $this->excerpt($request, $texts);

        if ($alerts === []) {
            return new PushParseResult('unparsed', null, [], 'No EventNotificationAlert found in the request.', $excerpt);
        }

        $events = [];
        $types = [];
        $summaries = [];

        foreach ($alerts as $alert) {
            $type = (string) ($alert['eventType'] ?? '');
            $types[] = $type;

            if (strcasecmp($type, 'heartBeat') === 0) {
                continue;
            }

            $access = $alert['AccessControllerEvent'] ?? null;
            if (! is_array($access)) {
                continue;
            }

            // The alert-level dateTime is the event time; some firmwares also repeat it inside the event.
            $info = [...$access, 'time' => $alert['dateTime'] ?? $access['time'] ?? null];
            $event = $this->mapper->map($info, $timezone);

            if ($event === null) {
                $summaries[] = sprintf('event %s/%s without person', $access['majorEventType'] ?? '?', $access['subEventType'] ?? '?');

                continue;
            }

            $events[] = new DeviceEvent(
                time: $event->time,
                employeeIdentifier: $event->employeeIdentifier,
                cardNumber: $event->cardNumber,
                type: $event->type,
                externalId: $event->externalId,
                payload: [
                    ...$event->payload,
                    'majorEventType' => $access['majorEventType'] ?? null,
                    'subEventType' => $access['subEventType'] ?? null,
                    'deviceID' => $alert['deviceID'] ?? null,
                    'source' => 'http_push',
                ],
            );
            $summaries[] = sprintf('%s %s', $event->employeeIdentifier ?? 'card '.$event->cardNumber, $event->time->setTimezone($timezone)->format('H:i:s'));
        }

        $kind = match (true) {
            $events !== [] => 'accepted',
            $types !== [] && collect($types)->every(fn ($t) => strcasecmp($t, 'heartBeat') === 0) => 'heartbeat',
            default => 'ignored',
        };

        return new PushParseResult($kind, implode(',', array_unique(array_filter($types))) ?: null, $events, implode('; ', array_slice($summaries, 0, 5)) ?: null, $excerpt);
    }

    /** @return list<string> */
    private function textParts(Request $request): array
    {
        $contentType = strtolower((string) $request->header('Content-Type'));

        if (! str_contains($contentType, 'multipart/')) {
            $body = (string) $request->getContent();

            return $body !== '' ? [$body] : [];
        }

        $texts = [];
        $fields = $request->request->all();
        array_walk_recursive($fields, function ($value) use (&$texts): void {
            if (is_string($value) && $value !== '') {
                $texts[] = $value;
            }
        });

        // Some firmwares send the alert as a "file" part (e.g. filename="AccessControllerEvent.json").
        foreach ($this->files($request) as $file) {
            $mime = strtolower((string) $file->getClientMimeType());
            $name = strtolower($file->getClientOriginalName());
            $looksText = str_contains($mime, 'json') || str_contains($mime, 'xml') || str_starts_with($mime, 'text/')
                || str_ends_with($name, '.json') || str_ends_with($name, '.xml');

            if ($looksText && $file->isValid() && $file->getSize() <= self::MAX_TEXT_PART) {
                $texts[] = (string) file_get_contents($file->getRealPath());
            }
        }

        return $texts;
    }

    /** @return list<UploadedFile> */
    private function files(Request $request): array
    {
        $files = [];
        $all = $request->allFiles();
        array_walk_recursive($all, function ($file) use (&$files): void {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        });

        return $files;
    }

    /** @return array<mixed>|null */
    private function decode(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '{') || str_starts_with($text, '[')) {
            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (str_starts_with($text, '<')) {
            try {
                $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/i', '', $text) ?? $text;
                $decoded = json_decode((string) json_encode(new SimpleXMLElement($xml, LIBXML_NONET | LIBXML_NOCDATA)), true);

                // SimpleXML drops the root element name; restore it so alertsIn() recognizes the alert.
                return is_array($decoded) ? ['EventNotificationAlert' => $this->trimStrings($decoded)] : null;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>|null  $data
     * @return list<array<string, mixed>>
     */
    private function alertsIn(?array $data): array
    {
        if ($data === null) {
            return [];
        }
        if (isset($data['EventNotificationAlert'])) {
            $inner = $data['EventNotificationAlert'];

            return array_is_list($inner) ? array_values(array_filter($inner, 'is_array')) : [$inner];
        }
        if (isset($data['eventType'])) {
            return [$data];
        }
        if (array_is_list($data)) {
            $alerts = [];
            foreach ($data as $item) {
                if (is_array($item)) {
                    array_push($alerts, ...$this->alertsIn($item));
                }
            }

            return $alerts;
        }

        return [];
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function trimStrings(array $data): array
    {
        array_walk_recursive($data, function (&$value): void {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        return $data;
    }

    /** @param list<string> $texts */
    private function excerpt(Request $request, array $texts): string
    {
        $parts = array_map(fn (string $t) => mb_substr($t, 0, 3000), $texts);

        foreach ($this->files($request) as $file) {
            $parts[] = sprintf('[file part: %s, %s, %d bytes — not stored]', $file->getClientOriginalName(), $file->getClientMimeType(), $file->getSize());
        }

        return mb_substr(implode("\n----\n", $parts), 0, 8000);
    }
}
