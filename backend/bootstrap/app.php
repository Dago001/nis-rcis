<?php

use App\Http\Middleware\EnsureActiveStaff;
use App\Http\Middleware\EnsureStaffRole;
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

        $middleware->alias([
            'scopes' => CheckToken::class,
            'scope' => CheckTokenForAnyScope::class,
            'client' => EnsureClientIsResourceOwner::class,
            'staff.active' => EnsureActiveStaff::class,
            'role' => EnsureStaffRole::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('password/*')
            ? route('login.staff')
            : null);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
