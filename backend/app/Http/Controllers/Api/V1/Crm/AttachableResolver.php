<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Resolves "{type}/{id}" owners for notes and documents through the tenant scope, then authorizes. */
final class AttachableResolver
{
    private const TYPES = [
        'customers' => [Customer::class, 'customers'],
        'leads' => [Lead::class, 'leads'],
        'deals' => [Deal::class, 'deals'],
        'employees' => [Employee::class, 'employees'],
    ];

    public static function resolve(string $type, int $id, User $user, bool $write): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$class] = self::TYPES[$type];

        /** @var Model $model */
        $model = $class::query()->findOrFail($id);

        if (! Gate::forUser($user)->allows($write ? 'update' : 'view', $model)) {
            throw new AuthorizationException;
        }

        return $model;
    }
}
