<?php

use App\Http\Helpers\ApiResponse;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureStatefulFrontendRequests;
use App\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'tenant.active' => EnsureTenantIsActive::class,
        ]);

        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy) => trim($proxy),
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies === ['*'] ? '*' : $trustedProxies);
        }

        $trustedHosts = array_values(array_filter(array_map(
            static fn (string $host) => trim($host),
            explode(',', (string) env('TRUSTED_HOSTS', ''))
        )));
        if ($trustedHosts !== []) {
            $middleware->trustHosts(at: $trustedHosts);
        }

        $middleware->web(
            prepend: AssignRequestId::class,
            append: AddSecurityHeaders::class,
        );
        $middleware->api(
            prepend: [AssignRequestId::class, EnsureStatefulFrontendRequests::class],
            append: AddSecurityHeaders::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $throwable): bool {
            return $request->is('api/*');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::unauthorized('Authentication is required.');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::forbidden($exception->getMessage() ?: 'You do not have permission to perform this action.');
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::validationError($exception->errors(), 'Validation failed.');
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::notFound('The requested resource could not be found.');
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::notFound('The requested endpoint could not be found.');
        });
    })->create();
