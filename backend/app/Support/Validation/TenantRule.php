<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules bound to the active tenant. Any id coming from the client
 * (customer_id, employee_id, assigned_to, ...) must be validated with these, never with a bare exists rule.
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id', bool $softDeletes = true): Exists
    {
        $rule = Rule::exists($table, $column)->where('organization_id', app(TenantContext::class)->id());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    public static function unique(string $table, string $column, ?int $ignoreId = null, bool $softDeletes = true): Unique
    {
        $rule = Rule::unique($table, $column)->where('organization_id', app(TenantContext::class)->id());
        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }

    /** A user of the current organization (for assigned_to / owner_id). */
    public static function user(): Exists
    {
        return self::exists('users');
    }
}
