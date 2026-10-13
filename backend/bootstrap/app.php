<?php

use App\Http\Middleware\EnsureActiveStaff;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\SecurityHeaders;
use App\Services\ErrorTracker;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::group([], base_path('routes/oauth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nginx (and Cloudflare in front of it) terminate TLS.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'scopes' => \App\Http\Middleware\CheckScopeOrSession::class,
            'scope' => CheckTokenForAnyScope::class,
            'client' => EnsureClientIsResourceOwner::class,
            'staff.active' => EnsureActiveStaff::class,
            'role' => EnsureStaffRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('staff', 'staff/*') || $request->is('password/*')) {
                return route('login.staff');
            }
            if ($request->is('portal', 'portal/*')) {
                return route('login.applicant');
            }
            return route('login');
        });

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error tracking: group, count and e-mail new server errors. The
        // normal log entry is still written.
        $exceptions->report(fn (Throwable $e) => app(ErrorTracker::class)->capture($e));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Database errors never reach a client, even with APP_DEBUG on:
        // they can contain SQL, table and column names. They are still logged.
        $exceptions->render(fn (QueryException $e, Request $request) => $request->is('api/*', 'oauth/*')
            ? response()->json(['message' => 'A server error occurred. Please try again later.'], 500)
            : null);
    })->create();
