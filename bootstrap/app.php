<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\GuardCrmAlertSection;
use App\Http\Middleware\GuardCrmSensitiveData;
use App\Http\Middleware\NoindexResponse;
use App\Http\Middleware\RequirePermission;
use App\Support\SensitiveFields;
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
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/auth')
                ->group(base_path('routes/api/auth.php'));

            Route::middleware(['api', 'auth:sanctum', 'active', 'permission:panel.rms'])
                ->prefix('api/rms')
                ->group(base_path('routes/api/rms.php'));

            Route::middleware(['api', 'auth:sanctum', 'active', 'permission:panel.crm', 'crm.sensitive'])
                ->prefix('api/crm')
                ->group(base_path('routes/api/crm.php'));

            Route::middleware(['api', 'auth:sanctum', 'active', 'permission:privacy.manage', 'crm.sensitive'])
                ->prefix('api/privacy')
                ->group(base_path('routes/api/privacy.php'));

            Route::middleware(['api', 'auth:sanctum', 'active', 'alerts.crm'])
                ->prefix('api')
                ->group(base_path('routes/api/alerts.php'));

            Route::middleware(['api', 'throttle:engine'])
                ->prefix('api/engine')
                ->group(base_path('routes/api/engine.php'));

            Route::middleware(['api', 'throttle:stripe-webhook'])
                ->prefix('api/stripe')
                ->group(base_path('routes/api/stripe.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/stripe/webhook',
            'api/engine/*',
        ]);
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*')) {
                return null;
            }

            return '/login';
        });
        $middleware->alias([
            'crm.sensitive' => GuardCrmSensitiveData::class,
            'alerts.crm' => GuardCrmAlertSection::class,
            'active' => EnsureUserIsActive::class,
            'permission' => RequirePermission::class,
            'noindex' => NoindexResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(SensitiveFields::all());
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
