<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Http\ApiErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            Route::middleware(['web', 'auth', 'verified', 'admin', 'two-factor.required', 'throttle:admin'])
                ->prefix(config('pacms.admin.path'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Sanctum cookie authentication for the same-domain SPA.
        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->api(append: [EnsureUserIsActive::class]);

        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'two-factor.required' => RequireTwoFactor::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One JSON error envelope for every API and JSON request (API-ARCHITECTURE.md §6.1).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'admin/api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*', 'admin/api/*') || $request->expectsJson()) {
                return ApiErrorRenderer::render($e, $request);
            }

            return null;
        });
    })->create();
