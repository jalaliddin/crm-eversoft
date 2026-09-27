<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Organization;

/**
 * Holds the organization the current request / job / command is acting for.
 *
 * Registered as a scoped singleton so it is reset between requests and queued jobs.
 * Tenant-owned models read it through OrganizationScope; nothing else should
 * derive the tenant from client input.
 */
final class TenantContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    public function require(): Organization
    {
        return $this->organization ?? throw new TenantNotResolvedException;
    }

    public function timezone(): string
    {
        return $this->organization?->timezone ?? config('app.default_tenant_timezone');
    }

    /**
     * Run a callback inside a specific tenant (scheduler, queue jobs, console), restoring the previous context.
     *
     * @template T
     *
     * @param  callable(Organization): T  $callback
     * @return T
     */
    public function run(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback($organization);
        } finally {
            $this->organization = $previous;
        }
    }
}
