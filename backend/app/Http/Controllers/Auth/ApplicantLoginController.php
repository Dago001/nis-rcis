<?php

namespace App\Http\Controllers\Auth;

use App\Models\Applicant;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Applicant sign-in on the OAuth2 authorization server.
 * Registration, e-mail verification and password reset are REST endpoints
 * used by the Next.js portal (see Api\Applicant\AccountController).
 */
class ApplicantLoginController
{
    public function show(): View
    {
        return view('auth.applicant-login', [
            'registerUrl' => config('nis.frontend_url').'/register',
            'forgotUrl' => config('nis.frontend_url').'/forgot-password',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $applicant = Applicant::where('email', strtolower($data['identifier']))->first();

        if (! $applicant || ! Hash::check($data['password'], $applicant->password)) {
            throw ValidationException::withMessages(['identifier' => 'Invalid e-mail address or password.']);
        }

        if (! $applicant->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'identifier' => 'Please verify your e-mail address first. Check your inbox for the verification link, or request a new one from the portal.',
            ]);
        }

        Auth::guard('applicant')->login($applicant);
        $request->session()->regenerate();
        Audit::log('APPLICANT_LOGIN', 'Signed in to the applicant portal', actor: $applicant);

        return redirect()->intended(config('nis.frontend_url').'/portal');
    }
}
