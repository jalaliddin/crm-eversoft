<?php

use App\Exceptions\BusinessRuleException;
use App\Exceptions\TenantMismatchException;
use App\Exceptions\TenantNotResolvedException;
use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'platform' => EnsurePlatformUser::class,
        ]);
        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->append(SecurityHeaders::class);
        // Tenant context must exist before implicit route-model binding runs,
        // so bound models are resolved through the tenant global scope.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([BusinessRuleException::class]);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $json = fn (string $message, int $status, array $errors = []) => response()->json(
            ['success' => false, 'message' => $message, 'errors' => (object) $errors],
            $status,
        );

        $exceptions->render(function (Throwable $e, Request $request) use ($json) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => $json('Validation failed.', 422, $e->errors()),
                $e instanceof BusinessRuleException => $json($e->getMessage(), 422, $e->errors()),
                $e instanceof AuthenticationException => $json('Unauthenticated.', 401),
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => $json(
                    $e->getMessage() && $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : 'You do not have permission to perform this action.',
                    403,
                ),
                $e instanceof TenantMismatchException, $e instanceof TenantNotResolvedException => $json('Organization access denied.', 403),
                $e instanceof ModelNotFoundException => $json('Resource not found.', 404),
                $e instanceof NotFoundHttpException => $json($e->getPrevious() instanceof ModelNotFoundException ? 'Resource not found.' : 'Endpoint not found.', 404),
                $e instanceof ThrottleRequestsException => $json('Too many requests. Please slow down.', 429),
                $e instanceof HttpExceptionInterface => $json($e->getMessage() ?: 'Request failed.', $e->getStatusCode()),
                default => $json(config('app.debug') ? $e->getMessage() : 'Server error.', 500),
            };
        });
    })->create();
