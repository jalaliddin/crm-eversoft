<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Carbon\CarbonImmutable;

/** Helpers for converting between UTC storage and tenant business time. */
final class TenantTime
{
    /** "+05:00" style offset of a timezone right now — used for SQL date bucketing (CONVERT_TZ needs no tz tables). */
    public static function sqlOffset(string $timezone): string
    {
        return CarbonImmutable::now($timezone)->format('P');
    }

    /**
     * Local date range → UTC instants [start of first day, end of last day].
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function utcRange(string $from, string $to, string $timezone): array
    {
        return [
            CarbonImmutable::parse($from, $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $timezone)->endOfDay()->utc(),
        ];
    }

    /** SQL expression for the local calendar date of a UTC timestamp column. */
    public static function localDateSql(string $column, string $timezone): string
    {
        $offset = self::sqlOffset($timezone);

        return "DATE(CONVERT_TZ({$column}, '+00:00', '{$offset}'))";
    }
}
