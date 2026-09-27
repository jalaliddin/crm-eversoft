<?php

declare(strict_types=1);

namespace App\Services\Attendance\Devices;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Prevents device addresses from being used to reach the server itself or cloud metadata (SSRF).
 * Tenants configure device hosts, so the address is checked at save time and again against the
 * resolved IPs before every request (DNS may change after validation).
 *
 * Private LAN/VPN ranges are allowed by default because terminals usually live there; set
 * ATTENDANCE_ALLOW_PRIVATE_NETWORKS=false on hosts where the server's own private network must stay unreachable.
 */
final class DeviceAddressGuard
{
    private const PRIVATE_RANGES = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'];

    public static function problem(string $host): ?string
    {
        $host = strtolower(trim($host, '[] '));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return 'Local addresses are not allowed.';
        }

        // Single-label names (e.g. "mysql", "redis") resolve to internal containers or intranet hosts.
        if (! filter_var($host, FILTER_VALIDATE_IP) && ! str_contains($host, '.')) {
            return 'Use an IP address or a fully qualified host name.';
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return null; // Unresolvable now; the request-time check runs again.
        }

        foreach ($ips as $ip) {
            if (IpUtils::checkIp($ip, self::blockedRanges())) {
                return 'This address points to the server itself or a reserved network.';
            }
            if (! config('attendance.allow_private_networks', true) && IpUtils::checkIp($ip, self::PRIVATE_RANGES)) {
                return 'Private network addresses are disabled on this server. Use the on-site bridge instead.';
            }
        }

        return null;
    }

    public static function assertAllowed(string $host): void
    {
        if ($problem = self::problem($host)) {
            throw new DeviceConnectionException('Device address rejected: '.$problem);
        }
    }

    /** @return list<string> */
    private static function blockedRanges(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('attendance.blocked_ranges')))));
    }
}
