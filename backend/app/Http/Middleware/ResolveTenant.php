<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\HostTenantResolver;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request → authenticated user → organization → TenantContext.
 *
 * The organization always comes from the authenticated user. When the request arrives on a
 * tenant host (subdomain / custom domain) it must match the user's organization.
 * Client-supplied organization ids are never consulted.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly HostTenantResolver $hostResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->organization_id === null) {
            abort(403, 'This endpoint is available to organization users only.');
        }

        $organization = $user->organization;

        if ($organization === null || ! $organization->isActive() || ! $user->isActive()) {
            abort(403, 'Your account or organization is not active.');
        }

        if ($this->hostResolver->isTenantHost($request)) {
            $hostOrganization = $this->hostResolver->resolve($request);

            if ($hostOrganization === null || $hostOrganization->id !== $organization->id) {
                Log::channel('security')->warning('Tenant host mismatch', [
                    'user_id' => $user->id,
                    'user_organization_id' => $organization->id,
                    'host' => $request->getHost(),
                ]);
                abort(403, 'You do not have access to this organization.');
            }
        }

        $this->context->set($organization);

        try {
            return $next($request);
        } finally {
            // Long-lived workers (Octane, queue, tests) must not carry the tenant into the next request.
            $this->context->set(null);
        }
    }
}
