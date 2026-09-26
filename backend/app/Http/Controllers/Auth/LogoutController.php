<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ends the authorization-server session (front-channel logout), so the next
 * /oauth/authorize prompts for credentials again. Tokens are revoked
 * separately by the frontend via POST /api/v1/oauth/revoke.
 */
class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $frontend = config('nis.frontend_url');
        $target = (string) $request->query('redirect_uri', $frontend);

        // Only ever redirect back to our own frontend.
        if ($target !== $frontend && ! Str::startsWith($target, $frontend.'/')) {
            $target = $frontend;
        }

        return redirect()->away($target);
    }
}
