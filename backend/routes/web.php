<?php

use App\Http\Controllers\Auth\ApplicantLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\OAuth\AuthorizeController;
use Illuminate\Support\Facades\Route;
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

Route::redirect('/', config('nis.frontend_url'));

Route::prefix('oauth')->name('passport.')->group(function () {
    // POST /oauth/token lives in routes/oauth.php (no session/CSRF middleware).

    Route::get('/authorize', [AuthorizeController::class, 'authorize'])
        ->middleware('web')
        ->name('authorizations.authorize');

    // Consent screen actions (third-party authorization-code clients only)
    Route::middleware(['web', 'auth:staff,applicant'])->group(function () {
        Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])->name('authorizations.approve');
        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });
});

Route::middleware('web')->group(function () {
    Route::get('/login/staff', [StaffLoginController::class, 'show'])->name('login.staff');
    Route::post('/login/staff', [StaffLoginController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:staff')->group(function () {
        Route::get('/password/change', [StaffLoginController::class, 'showChangePassword'])->name('password.change');
        Route::post('/password/change', [StaffLoginController::class, 'changePassword'])->middleware('throttle:login');
    });

    Route::get('/login/applicant', [ApplicantLoginController::class, 'show'])->name('login.applicant');
    Route::post('/login/applicant', [ApplicantLoginController::class, 'login'])->middleware('throttle:login');

    Route::get('/logout', LogoutController::class)->name('logout');
});
