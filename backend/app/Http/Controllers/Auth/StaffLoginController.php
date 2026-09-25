<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\Audit;
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

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('STAFF_LOGIN', 'Signed in to the staff console', actor: $user);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(config('nis.frontend_url').'/staff');
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
