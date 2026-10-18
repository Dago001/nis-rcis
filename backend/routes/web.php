<?php

use App\Http\Controllers\Auth\ApplicantLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\OAuth\AuthorizeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
|--------------------------------------------------------------------------
| OAuth2 authorization server
|--------------------------------------------------------------------------
|
| Grants enabled:
|  - authorization_code (+ PKCE) + refresh_token : Next.js staff console and
|    applicant portal (first-party, confidential clients)
|  - client_credentials : partner agencies calling the card verification API
|
| Password and implicit grants are intentionally NOT enabled.
|
*/

Route::middleware('web')->group(function () {
    // ------------------------------------------------ Public Pages (Inertia)
    Route::get('/', fn () => Inertia::render('Public/Home'))->name('home');
    Route::get('/about', fn () => Inertia::render('Public/About'))->name('about');
    Route::get('/contact', fn () => Inertia::render('Public/Contact'))->name('contact');
    Route::get('/faq', fn () => Inertia::render('Public/Faq'))->name('faq');
    Route::get('/privacy', fn () => Inertia::render('Public/Privacy'))->name('privacy');
    Route::get('/track', fn () => Inertia::render('Public/Track'))->name('track');
    Route::get('/verify', fn () => Inertia::render('Public/Verify'))->name('verify');
    Route::get('/queue-display/{center}', fn ($center) => Inertia::render('Public/QueueDisplay', ['center' => $center]))->name('queue-display');
    Route::get('/favicon.ico', function () {
        $path = public_path('favicon.ico');
        if (! file_exists($path)) {
            $path = public_path('favicon-32x32.png');
        }
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/x-icon';
        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    });

    Route::match(['get', 'post'], '/locale/{lang}', function (string $lang) {
        $valid = in_array($lang, ['en', 'fr'], true) ? $lang : 'en';
        session(['locale' => $valid]);
        app()->setLocale($valid);
        $cookie = cookie('nis_lang', $valid, 60 * 24 * 365, '/', null, false, false, false, 'lax');
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['locale' => $valid])->withCookie($cookie);
        }
        return back()->withCookie($cookie);
    })->name('locale.switch');

    // -------------------------------------------------- Auth Pages (Inertia)
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('forgot-password');
    Route::get('/reset-password', fn () => Inertia::render('Auth/ResetPassword'))->name('reset-password');
    Route::get('/auth-error', fn () => Inertia::render('Auth/AuthError'))->name('auth-error');

    // ------------------------------------------------ OAuth2 Authorization Server
    Route::prefix('oauth')->name('passport.')->group(function () {
        Route::get('/authorize', [AuthorizeController::class, 'authorize'])->name('authorizations.authorize');
        Route::middleware('auth:staff,applicant')->group(function () {
            Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])->name('authorizations.approve');
            Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
        });
    });

    // --------------------------------------- Native Sign-In & Session Actions
    Route::get('/login/staff', [StaffLoginController::class, 'show'])->name('login.staff');
    Route::post('/login/staff', [StaffLoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/login/staff/two-factor', [StaffLoginController::class, 'showTwoFactor'])->name('login.staff.two-factor');
    Route::post('/login/staff/two-factor', [StaffLoginController::class, 'verifyTwoFactor'])->middleware('throttle:two-factor');

    Route::middleware('auth:staff')->group(function () {
        Route::get('/login/staff/change-password', [StaffLoginController::class, 'showChangePassword'])->name('password.change');
        Route::post('/login/staff/change-password', [StaffLoginController::class, 'changePassword'])->middleware('throttle:login');
        Route::get('/staff/change-password', [StaffLoginController::class, 'showChangePassword']);
        Route::post('/staff/change-password', [StaffLoginController::class, 'changePassword'])->middleware('throttle:login');
        Route::get('/password/change', [StaffLoginController::class, 'showChangePassword']);
        Route::post('/password/change', [StaffLoginController::class, 'changePassword'])->middleware('throttle:login');
    });

    Route::get('/login/applicant', [ApplicantLoginController::class, 'show'])->name('login.applicant');
    Route::post('/login/applicant', [ApplicantLoginController::class, 'login'])->middleware('throttle:login');

    Route::get('/logout', LogoutController::class)->name('logout');
    Route::post('/logout', LogoutController::class);

    // ---------------------------------------------- Slip Route (Applicant & Staff)
    Route::get('/portal/applications/{id}/slip', fn ($id) => Inertia::render('Portal/Slip', ['id' => $id]))
        ->middleware('auth:applicant,staff')
        ->whereNumber('id')
        ->name('portal.applications.slip');

    // ---------------------------------------------- Applicant Portal (Inertia)
    Route::middleware('auth:applicant')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', fn () => Inertia::render('Portal/Dashboard'))->name('dashboard');
        Route::get('/apply', fn () => Inertia::render('Portal/Apply'))->name('apply');
        Route::get('/applications/{id}', fn ($id) => Inertia::render('Portal/ApplicationDetail', ['id' => $id]))->whereNumber('id')->name('applications.show');
        Route::get('/cards', fn () => Inertia::render('Portal/Cards'))->name('cards');
        Route::get('/payments', fn () => Inertia::render('Portal/Payments'))->name('payments');
        Route::get('/notifications', fn () => Inertia::render('Portal/Notifications'))->name('notifications');
    });

    // ------------------------------------------------- Staff Console (Inertia)
    Route::middleware(['auth:staff', 'staff.active'])->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', fn () => Inertia::render('Staff/Dashboard'))->name('dashboard');
        Route::get('/queue', fn () => Inertia::render('Staff/Queue'))->name('queue');
        Route::get('/approvals', fn () => Inertia::render('Staff/Approvals'))->name('approvals');
        Route::get('/applications', fn () => Inertia::render('Staff/Applications'))->name('applications');
        Route::get('/applications/new', fn () => Inertia::render('Staff/ApplicationNew'))->name('applications.new');
        Route::get('/applications/{id}', fn ($id) => Inertia::render('Staff/ApplicationDetail', ['id' => $id]))->whereNumber('id')->name('applications.show');
        Route::get('/applications/{id}/biometrics', fn ($id) => Inertia::render('Staff/Biometrics', ['id' => $id]))->whereNumber('id')->name('applications.biometrics');
        Route::get('/cards', fn () => Inertia::render('Staff/Cards'))->name('cards');
        Route::get('/cards/print-batch', fn () => Inertia::render('Staff/CardPrintBatch'))->name('cards.print-batch');
        Route::get('/cards/{id}', fn ($id) => Inertia::render('Staff/CardDetail', ['id' => $id]))->whereNumber('id')->name('cards.show');
        Route::get('/cards/{id}/print', fn ($id) => Inertia::render('Staff/CardPrint', ['id' => $id]))->whereNumber('id')->name('cards.print');
        Route::get('/card-stock', fn () => Inertia::render('Staff/CardStock'))->name('card-stock');
        Route::get('/payments', fn () => Inertia::render('Staff/Payments'))->name('payments');
        Route::get('/payments/{id}', fn ($id) => Inertia::render('Staff/PaymentDetail', ['id' => $id]))->name('payments.show');
        Route::get('/refunds', fn () => Inertia::render('Staff/Refunds'))->name('refunds');
        Route::get('/reports', fn () => Inertia::render('Staff/Reports'))->name('reports');
        Route::get('/audit-logs', fn () => Inertia::render('Staff/AuditLogs'))->name('audit-logs');
        Route::get('/data-breaches', fn () => Inertia::render('Staff/DataBreaches'))->name('data-breaches');
        Route::get('/users', fn () => Inertia::render('Staff/Users'))->name('users');
        Route::get('/system', fn () => Inertia::render('Staff/System'))->name('system');
        Route::get('/settings', fn () => Inertia::render('Staff/Settings'))->name('settings');
    });
});
