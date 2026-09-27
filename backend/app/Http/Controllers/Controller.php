<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Http\ApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponses, AuthorizesRequests;

    /** Clamp client-requested page size. */
    protected function perPage(Request $request, int $default = 25): int
    {
        return max(1, min(100, (int) $request->integer('per_page', $default)));
    }

    /**
     * Resolve a whitelisted sort column and direction from ?sort=field or ?sort=-field.
     *
     * @param  list<string>  $allowed
     * @return array{0: string, 1: string}
     */
    protected function sort(Request $request, array $allowed, string $default = '-created_at'): array
    {
        $sort = (string) $request->query('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = ltrim($default, '-');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        return [$column, $direction];
    }
}
