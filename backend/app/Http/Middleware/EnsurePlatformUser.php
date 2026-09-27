<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isPlatformUser() || ! $user->isActive()) {
            abort(403, 'Platform administrators only.');
        }

        // Platform endpoints always run without a tenant scope.
        app(TenantContext::class)->set(null);

        return $next($request);
    }
}
