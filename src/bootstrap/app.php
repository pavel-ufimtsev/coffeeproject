<?php

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LeaveInertiaForBladePages;
use App\Http\Middleware\RedirectLegacyUrls;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\LoginResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // T-Bank signs its notifications instead (see PaymentController).
        $middleware->validateCsrfTokens(except: ['api/tinkoff/webhook']);

        // Global, so old URLs that match no route are redirected before routing 404s them.
        $middleware->prepend(RedirectLegacyUrls::class);

        $middleware->web(append: [
            SetLocale::class,
            AddLinkHeadersForPreloadedAssets::class,
            LeaveInertiaForBladePages::class,
        ]);

        // Everything under /admin: signed-in, verified admins only, rendered with Inertia.
        $middleware->group('admin', [
            'auth',
            'can:access-admin',
            'verified',
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnsureTwoFactorIsEnabled::class,
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => LoginResponse::home($request));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
