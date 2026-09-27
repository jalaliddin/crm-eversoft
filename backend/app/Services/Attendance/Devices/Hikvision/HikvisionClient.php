<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices\Hikvision;

use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceAddressGuard;
use App\Services\Attendance\Devices\DeviceConnectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Thin ISAPI transport: HTTP digest auth, timeouts, retry on network errors, structured logging.
 * Knows nothing about employees or attendance.
 *
 * Endpoints used (ISAPI, as deployed against DS-K1A340FWX in production elsewhere — see docs/HIKVISION.md):
 *   GET  /ISAPI/System/deviceInfo
 *   POST /ISAPI/AccessControl/AcsEvent?format=json
 *   POST /ISAPI/AccessControl/UserInfo/Search?format=json
 *   POST /ISAPI/AccessControl/UserInfo/Record?format=json
 *   PUT  /ISAPI/AccessControl/UserInfo/Modify?format=json
 *   PUT  /ISAPI/AccessControl/UserInfo/Delete?format=json
 */
class HikvisionClient
{
    public function __construct(private readonly AttendanceDevice $device) {}

    /** @return array<string, mixed> */
    public function getXml(string $path): array
    {
        return $this->parseXml($this->send('GET', $path)->body());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $body): array
    {
        return $this->send('POST', $path, $body)->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function putJson(string $path, array $body): array
    {
        return $this->send('PUT', $path, $body)->json() ?? [];
    }

    /** @param array<string, mixed>|null $body */
    private function send(string $method, string $path, ?array $body = null): Response
    {
        if (! $this->device->ip_address) {
            throw new DeviceConnectionException('Device address is not configured.');
        }
        DeviceAddressGuard::assertAllowed($this->device->ip_address);

        $started = microtime(true);
        $context = ['device_id' => $this->device->id, 'organization_id' => $this->device->organization_id, 'method' => $method, 'path' => $path];

        try {
            $response = $this->request()->send($method, $this->device->baseUrl().$path, $body !== null ? ['json' => $body] : []);
        } catch (ConnectionException $e) {
            Log::channel('hikvision')->warning('Device unreachable', [...$context, 'error' => $e->getMessage()]);

            throw new DeviceConnectionException('Device unreachable: '.$e->getMessage(), previous: $e);
        } catch (RequestException $e) {
            $response = $e->response;
        } catch (Throwable $e) {
            Log::channel('hikvision')->error('Device request failed', [...$context, 'error' => $e->getMessage()]);

            throw new DeviceConnectionException('Device request failed: '.$e->getMessage(), previous: $e);
        }

        $context['status'] = $response->status();
        $context['duration_ms'] = (int) ((microtime(true) - $started) * 1000);

        if ($response->status() === 401) {
            Log::channel('hikvision')->warning('Device authentication failed', $context);

            throw new DeviceConnectionException('Authentication failed: check device username/password.');
        }

        if (! $response->successful()) {
            $subStatus = $response->json('subStatusCode') ?? $this->parseXml($response->body())['subStatusCode'] ?? null;
            Log::channel('hikvision')->warning('Device returned an error', [...$context, 'sub_status' => $subStatus]);

            throw new HikvisionRequestException(
                sprintf('Device returned HTTP %d%s.', $response->status(), $subStatus ? " ({$subStatus})" : ''),
                $response->status(),
                is_string($subStatus) ? $subStatus : null,
            );
        }

        Log::channel('hikvision')->debug('Device request ok', $context);

        return $response;
    }

    private function request(): PendingRequest
    {
        $config = config('attendance.hikvision');

        return Http::withDigestAuth((string) $this->device->username, (string) $this->device->encrypted_password)
            ->acceptJson()
            ->timeout($config['timeout'])
            ->connectTimeout($config['connect_timeout'])
            ->withOptions(['verify' => $config['verify_tls']])
            // Retry only transport errors; HTTP errors are deterministic and returned as-is.
            ->retry(
                $config['retries'] + 1,
                $config['retry_sleep_ms'],
                fn (Throwable $e) => $e instanceof ConnectionException,
                throw: false,
            );
    }

    /** @return array<string, mixed> */
    private function parseXml(string $xml): array
    {
        if (trim($xml) === '' || ! str_starts_with(ltrim($xml), '<')) {
            return [];
        }

        try {
            $xml = preg_replace('/\sxmlns[^=]*="[^"]*"/i', '', $xml) ?? $xml;
            $decoded = json_decode((string) json_encode(new SimpleXMLElement($xml, LIBXML_NONET)), true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }
}
