<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Applicant;
use App\Models\Application;
use App\Support\ApplicationRules;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AccountController
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'surname' => ['required', 'string', 'max:100', 'regex:'.ApplicationRules::NAME],
            'forenames' => ['required', 'string', 'max:150', 'regex:'.ApplicationRules::NAME],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.ApplicationRules::PHONE],
            'password' => ['required', 'confirmed', PasswordRule::min(6)->letters()->numbers()->uncompromised()],
            'privacy_consent' => ['accepted'],
        ], ['privacy_consent.accepted' => 'You must read and accept the privacy notice to create an account.']);

        $email = strtolower($data['email']);

        // Same response whether or not the address is taken (no account enumeration).
        if (! Applicant::where('email', $email)->exists()) {
            $applicant = Applicant::create([
                'email' => $email,
                'password' => $data['password'],
                'surname' => mb_strtoupper(trim($data['surname'])),
                'forenames' => mb_strtoupper(trim($data['forenames'])),
                'phone' => $data['phone'],
            ]);
            $applicant->forceFill(['privacy_consent_at' => now(), 'privacy_policy_version' => config('nis.privacy_policy_version')])->save();
            if (Features::skipEmailVerification()) {
                $applicant->markEmailAsVerified();
            } else {
                $applicant->sendEmailVerificationNotification();
            }
            Audit::log('APPLICANT_REGISTERED', 'Applicant account created', $applicant, actor: $applicant);
        }

        return response()->json([
            'message' => Features::skipEmailVerification()
                ? 'Test mode: e-mail verification is switched off. If this e-mail address was not already registered, your account is ready - you can sign in now.'
                : 'If this e-mail address can be used, a verification link has been sent to it. Verify your e-mail, then sign in.',
        ], 202);
    }

    /**
     * Signed link from the verification e-mail. Redirects to the portal.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $applicant = Applicant::findOrFail($id);
        $login = config('nis.frontend_url').'/login';

        if (! hash_equals(sha1($applicant->getEmailForVerification()), $hash)) {
            return redirect()->away($login.'?verified=invalid');
        }

        if (! $applicant->hasVerifiedEmail()) {
            $applicant->markEmailAsVerified();
            Audit::log('APPLICANT_EMAIL_VERIFIED', 'E-mail address verified', $applicant, actor: $applicant);
        }

        return redirect()->away($login.'?verified=1');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $applicant = Applicant::where('email', strtolower($data['email']))->first();

        if ($applicant && ! $applicant->hasVerifiedEmail()) {
            $applicant->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If the account exists and is unverified, a new link has been sent.'], 202);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::broker('applicants')->sendResetLink(['email' => strtolower($data['email'])]);

        return response()->json(['message' => 'If the account exists, a password reset link has been sent.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(6)->letters()->numbers()->uncompromised()],
        ]);

        $status = Password::broker('applicants')->reset(
            ['email' => strtolower($data['email'])] + $data,
            function (Applicant $applicant, string $password) {
                $applicant->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                // A reset is also proof of mailbox ownership.
                if (! $applicant->hasVerifiedEmail()) {
                    $applicant->markEmailAsVerified();
                }
                $applicant->tokens()->update(['revoked' => true]);
                event(new PasswordReset($applicant));
                Audit::log('APPLICANT_PASSWORD_RESET', 'Password reset via e-mail link', $applicant, actor: $applicant);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    /**
     * Everything the Service holds about the signed-in applicant, as JSON
     * (NDPA 2023 right of access and data portability).
     */
    public function myData(Request $request): JsonResponse
    {
        /** @var Applicant $applicant */
        $applicant = $request->user();
        $applicant->load(['applications.statusHistory', 'applications.documents', 'applications.payments', 'applications.enrollmentCenter']);

        Audit::log('APPLICANT_DATA_EXPORTED', 'Applicant downloaded their personal data', $applicant, actor: $applicant);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'controller' => config('nis.organization'),
            'account' => [
                ...$applicant->only(['email', 'surname', 'forenames', 'phone', 'nationality', 'passport_number', 'created_at', 'email_verified_at']),
                'privacy_consent_at' => $applicant->privacy_consent_at?->toIso8601String(),
                'privacy_policy_version' => $applicant->privacy_policy_version,
            ],
            'applications' => $applicant->applications->map(fn ($a) => [
                ...collect($a->only([...Application::PARTICULARS, 'application_number', 'reference_number', 'type', 'phone', 'email', 'appointment_date', 'appointment_time', 'submitted_at', 'decided_at', 'decision_notes']))->all(),
                'status' => $a->status->value,
                'enrollment_center' => $a->enrollmentCenter?->name,
                'history' => $a->statusHistory->map(fn ($h) => ['to' => $h->to_status->value, 'at' => $h->created_at?->toIso8601String(), 'notes' => $h->notes]),
                'documents' => $a->documents->map(fn ($d) => ['type' => $d->type->label(), 'file_name' => $d->original_name, 'uploaded_at' => $d->created_at?->toIso8601String()]),
                'payments' => $a->payments->map(fn ($p) => ['reference' => $p->reference, 'amount_naira' => $p->amount_kobo / 100, 'status' => $p->status, 'paid_at' => $p->paid_at?->toIso8601String()]),
            ]),
        ], 200, ['Content-Disposition' => 'attachment; filename="my-nis-rcis-data.json"']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Applicant $applicant */
        $applicant = $request->user();

        return response()->json([
            'id' => $applicant->id,
            'email' => $applicant->email,
            'surname' => $applicant->surname,
            'forenames' => $applicant->forenames,
            'phone' => $applicant->phone,
            'nationality' => $applicant->nationality,
            'passport_number' => $applicant->passport_number,
            'has_draft' => $applicant->draft()->exists(),
            'unread_notifications' => $applicant->unreadNotifications()->count(),
        ]);
    }
}
