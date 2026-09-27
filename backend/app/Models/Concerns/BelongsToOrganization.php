<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned.
 *
 * - Reads are scoped to the active tenant by a global scope.
 * - organization_id is filled from the tenant context on create and can never be
 *   set to another tenant or changed afterwards.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            $contextId = app(TenantContext::class)->id();

            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', $contextId);
            } elseif ($contextId !== null && (int) $model->getAttribute('organization_id') !== $contextId) {
                throw new TenantMismatchException;
            }

            if ($model->getAttribute('organization_id') === null) {
                throw new TenantMismatchException('Tenant-owned record created without an organization.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id')) {
                throw new TenantMismatchException('organization_id is immutable.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
