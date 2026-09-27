<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Resolves an organization from the request host:
 *   {slug}.{TENANT_BASE_DOMAIN}  (e.g. acme.crm.eversoft.uz)
 *   a custom domain stored in organizations.domain
 * The bare base domain (crm.eversoft.uz) resolves to nothing and the user's organization is used.
 */
class HostTenantResolver
{
    public function resolve(Request $request): ?Organization
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('tenancy.base_domain'));

        if ($base === '' || $host === $base || in_array($host, config('tenancy.central_domains', []), true)) {
            return null;
        }

        if (str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));

            return $slug !== '' && ! str_contains($slug, '.')
                ? Organization::query()->where('slug', $slug)->first()
                : null;
        }

        return Organization::query()->where('domain', $host)->first();
    }

    public function isTenantHost(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('tenancy.base_domain'));

        if ($base === '' || $host === $base || in_array($host, config('tenancy.central_domains', []), true)) {
            return false;
        }

        return str_ends_with($host, '.'.$base) || Organization::query()->where('domain', $host)->exists();
    }
}
