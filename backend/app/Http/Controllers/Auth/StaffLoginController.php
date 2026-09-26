<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\Audit;
use App\Support\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff sign-in on the OAuth2 authorization server.
 */
class StaffLoginController
{
    public function show(): View
    {
        return view('auth.staff-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::query()
            ->where('service_number', $data['identifier'])
            ->orWhere('username', $data['identifier'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Audit::log('STAFF_LOGIN_FAILED', 'Failed staff sign-in attempt', context: ['identifier' => $data['identifier']], actorLabel: 'GUEST');

            throw ValidationException::withMessages(['identifier' => 'Invalid service number/username or password.']);
        }

        if (! $user->is_active) {
            Audit::log('STAFF_LOGIN_BLOCKED', 'Sign-in attempt on a deactivated account', actor: $user);

            throw ValidationException::withMessages(['identifier' => 'This account has been deactivated. Contact the system administrator.']);
        }

        // Second step: a code from the officer's authenticator app.
        if ($this->twoFactorRequired()) {
            $request->session()->regenerate();
            $request->session()->put('staff_2fa', ['id' => $user->id, 'at' => time()]);
            $request->session()->forget('staff_2fa_secret');

            return redirect()->route('login.staff.two-factor');
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Enter the authenticator code, or set up the authenticator app on the
     * first sign-in (or after the Super Admin reset it).
     */
    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login.staff')->withErrors(['identifier' => 'Please sign in again.']);
        }

        if ($user->hasTwoFactor()) {
            return view('auth.two-factor', ['setup' => false]);
        }

        $secret = $request->session()->get('staff_2fa_secret') ?? tap(Totp::generateSecret(), fn ($s) => $request->session()->put('staff_2fa_secret', $s));
        $uri = Totp::uri($user->service_number, $secret);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($uri);

        return view('auth.two-factor', ['setup' => true, 'qr' => $qr, 'secret' => trim(chunk_split($secret, 4, ' '))]);
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login.staff')->withErrors(['identifier' => 'Please sign in again.']);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $setup = ! $user->hasTwoFactor();
        $secret = $setup ? (string) $request->session()->get('staff_2fa_secret') : (string) $user->two_factor_secret;
        $step = $secret !== '' ? Totp::verify($secret, $data['code'], $user->two_factor_last_step) : null;

        if ($step === null) {
            Audit::log('STAFF_2FA_FAILED', 'Wrong authenticator code', actor: $user);

            throw ValidationException::withMessages(['code' => 'That code is not correct. Enter the current 6-digit code from your authenticator app.']);
        }

        $user->forceFill([
            'two_factor_last_step' => $step,
            ...($setup ? ['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()] : []),
        ])->save();

        if ($setup) {
            Audit::log('STAFF_2FA_ENABLED', 'Set up authenticator app sign-in', actor: $user);
        }

        $request->session()->forget(['staff_2fa', 'staff_2fa_secret']);

        return $this->completeLogin($request, $user);
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('STAFF_LOGIN', 'Signed in to the staff console', actor: $user);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(config('nis.frontend_url').'/staff');
    }

    /** The officer who passed the password step within the last 10 minutes. */
    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('staff_2fa');
        if (! is_array($pending) || time() - (int) ($pending['at'] ?? 0) > 600) {
            return null;
        }
        $user = User::find($pending['id'] ?? 0);

        return $user && $user->is_active ? $user : null;
    }

    private function twoFactorRequired(): bool
    {
        return config('nis.staff_two_factor') || app()->isProduction();
    }

    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('staff')->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:staff'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $request->session()->regenerate();
        Audit::log('STAFF_PASSWORD_CHANGED', 'Changed own password', actor: $user);

        return redirect()->intended(config('nis.frontend_url').'/staff');
    }
}
