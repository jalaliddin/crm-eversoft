<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(TenantContext::class)->id();

        if ($organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }
    }
}
