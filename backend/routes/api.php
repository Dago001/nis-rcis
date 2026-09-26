<?php

use App\Http\Controllers\Api\Applicant\AccountController;
use App\Http\Controllers\Api\Applicant\ApplicationController as ApplicantApplications;
use App\Http\Controllers\Api\Applicant\DraftController;
use App\Http\Controllers\Api\Applicant\PaymentController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Public\PartnerController;
use App\Http\Controllers\Api\Public\PaystackWebhookController;
use App\Http\Controllers\Api\Public\PublicController;
use App\Http\Controllers\Api\Staff\ApplicationController as StaffApplications;
use App\Http\Controllers\Api\Staff\ApprovalController;
use App\Http\Controllers\Api\Staff\AuditLogController;
use App\Http\Controllers\Api\Staff\CardController;
use App\Http\Controllers\Api\Staff\DashboardController;
use App\Http\Controllers\Api\Staff\DataBreachController;
use App\Http\Controllers\Api\Staff\PaymentController as StaffPayments;
use App\Http\Controllers\Api\Staff\ReportController;
use App\Http\Controllers\Api\Staff\UserController;
use App\Http\Controllers\OAuth\RevokeTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NIS-RCIS REST API v1
|--------------------------------------------------------------------------
|
| Authentication is OAuth2 (Laravel Passport) bearer tokens:
|   staff      -> auth:api           + scopes:staff      (+ role checks)
|   applicants -> auth:applicant-api + scopes:applicant
|   partners   -> client:cards:verify (client_credentials grant)
|
| Role permissions mirror the legacy requireRole() calls.
|
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------- Public
    Route::prefix('public')->group(function () {
        Route::get('enrollment-centers', [PublicController::class, 'centers']);
        Route::get('enrollment-centers/{center}/availability', [PublicController::class, 'availability']);

        Route::middleware('throttle:public-lookup')->group(function () {
            Route::get('track', [PublicController::class, 'track']);
            Route::get('verify-card', [PublicController::class, 'verifyCard']);
        });

        Route::post('assistant', [AssistantController::class, 'guest'])->middleware('throttle:assistant');
    });

    Route::post('webhooks/paystack', PaystackWebhookController::class);

    // ------------------------------------------------- Applicant account (guest)
    Route::prefix('applicant')->group(function () {
        Route::middleware('throttle:registration')->group(function () {
            Route::post('register', [AccountController::class, 'register']);
            Route::post('email/resend', [AccountController::class, 'resendVerification']);
            Route::post('password/forgot', [AccountController::class, 'forgotPassword']);
        });
        Route::post('password/reset', [AccountController::class, 'resetPassword'])->middleware('throttle:login');
        Route::get('email/verify/{id}/{hash}', [AccountController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('applicant.verify');
    });

    // ------------------------------------------------------ Applicant portal
    Route::prefix('applicant')->middleware(['auth:applicant-api', 'scopes:applicant'])->group(function () {
        Route::get('me', [AccountController::class, 'me']);
        Route::get('my-data', [AccountController::class, 'myData'])->middleware('throttle:6,1');
        Route::post('oauth/revoke', RevokeTokenController::class);

        Route::get('draft', [DraftController::class, 'show']);
        Route::put('draft', [DraftController::class, 'save']);
        Route::delete('draft', [DraftController::class, 'destroy']);
        Route::post('draft/documents', [DraftController::class, 'uploadDocument']);
        Route::get('draft/documents/{document}', [DraftController::class, 'document'])->whereNumber('document');

        Route::post('payments', [PaymentController::class, 'initialize']);
        Route::post('payments/{reference}/verify', [PaymentController::class, 'verify']);

        Route::get('applications', [ApplicantApplications::class, 'index']);
        Route::post('applications', [ApplicantApplications::class, 'store']);
        Route::get('applications/{id}', [ApplicantApplications::class, 'show'])->whereNumber('id');
        Route::get('applications/{id}/slip', [ApplicantApplications::class, 'slip'])->whereNumber('id');
        Route::post('applications/{id}/documents', [ApplicantApplications::class, 'uploadDocument'])->whereNumber('id');
        Route::get('applications/{id}/documents/{document}', [ApplicantApplications::class, 'document'])->whereNumber(['id', 'document']);
        Route::post('applications/{id}/respond', [ApplicantApplications::class, 'respondToQuery'])->whereNumber('id');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markRead']);

        Route::post('assistant', [AssistantController::class, 'applicant'])->middleware('throttle:assistant');
    });

    // ------------------------------------------------------- Staff console
    Route::prefix('staff')->middleware(['auth:api', 'scopes:staff', 'staff.active'])->group(function () {
        Route::get('me', [DashboardController::class, 'me']);
        Route::get('dashboard', [DashboardController::class, 'summary']);
        Route::post('oauth/revoke', RevokeTokenController::class);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markRead']);

        // Approval queue & application records (all staff can view)
        Route::get('applications', [StaffApplications::class, 'index']);
        Route::get('applications/{id}', [StaffApplications::class, 'show'])->whereNumber('id');
        Route::get('applications/{id}/documents/{document}', [StaffApplications::class, 'document'])->whereNumber(['id', 'document']);
        Route::post('applications/{id}/risk-check', [StaffApplications::class, 'riskCheck'])->whereNumber('id');

        Route::middleware('role:SuperAdmin')->group(function () {
            Route::post('applications', [StaffApplications::class, 'store']);
            Route::post('applications/{id}/documents', [StaffApplications::class, 'uploadDocument'])->whereNumber('id');
        });

        Route::post('applications/{id}/decision', [StaffApplications::class, 'decide'])
            ->middleware('role:ApprovingOfficer')->whereNumber('id');

        Route::middleware('role:ApprovingOfficer,IssuingOfficer')->group(function () {
            Route::post('applications/{id}/biometrics', [StaffApplications::class, 'captureBiometrics'])->whereNumber('id');
            Route::post('applications/{id}/collect', [StaffApplications::class, 'collect'])->whereNumber('id');
        });

        // Fee payments (visible as soon as Paystack confirms them)
        Route::get('payments', [StaffPayments::class, 'index']);
        Route::get('payments/{id}', [StaffPayments::class, 'show'])->whereNumber('id');

        // Residence card register
        Route::get('cards', [CardController::class, 'index']);
        Route::get('cards/{id}', [CardController::class, 'show'])->whereNumber('id');

        Route::middleware('role:ApprovingOfficer,IssuingOfficer')->group(function () {
            Route::get('cards/{id}/print', [CardController::class, 'print'])->whereNumber('id');
            Route::put('cards/{id}', [CardController::class, 'update'])->whereNumber('id');
            Route::post('cards/{id}/ready-for-collection', [CardController::class, 'readyForCollection'])->whereNumber('id');
            Route::post('cards/{id}/renew', [CardController::class, 'renew'])->whereNumber('id');
        });

        Route::middleware('role:ApprovingOfficer')->group(function () {
            Route::post('cards/{id}/decision', [CardController::class, 'decide'])->whereNumber('id');
            Route::post('cards/{id}/revoke', [CardController::class, 'revoke'])->whereNumber('id');
            Route::post('cards/{id}/watchlist', [CardController::class, 'watchlist'])->whereNumber('id');
        });

        Route::post('cards/{id}/reinstate', [CardController::class, 'reinstate'])
            ->middleware('role:SuperAdmin')->whereNumber('id');

        // Two-person rule: requests waiting for a second officer
        Route::get('approvals', [ApprovalController::class, 'index']);
        Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve'])->whereNumber('id');
        Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject'])->whereNumber('id');
        Route::post('approvals/{id}/cancel', [ApprovalController::class, 'cancel'])->whereNumber('id');

        // Reports
        Route::get('reports/summary', [ReportController::class, 'summary']);
        Route::get('reports/export', [ReportController::class, 'export'])->middleware('role:SuperAdmin');

        // Administration
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:Auditor');

        // NDPA personal-data breach register
        Route::get('data-breaches', [DataBreachController::class, 'index'])->middleware('role:Auditor');
        Route::post('data-breaches', [DataBreachController::class, 'store'])->middleware('role:SuperAdmin');
        Route::patch('data-breaches/{id}', [DataBreachController::class, 'update'])->middleware('role:SuperAdmin')->whereNumber('id');

        Route::middleware('role:SuperAdmin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::patch('users/{id}', [UserController::class, 'update'])->whereNumber('id');
            Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('id');
            Route::post('users/{id}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->whereNumber('id');
            Route::post('users/{id}/revoke-sessions', [UserController::class, 'revokeSessions'])->whereNumber('id');
        });
    });

    // ------------------------------------------------ Partner agencies (M2M)
    Route::prefix('partner')->middleware(['client:cards:verify', 'throttle:api'])->group(function () {
        Route::get('cards/verify', [PartnerController::class, 'verifyCard']);
    });
});
