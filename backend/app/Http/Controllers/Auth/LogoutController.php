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

        $target = (string) $request->query('redirect_uri', '/');

        // Only redirect to local paths or trusted frontend
        $frontend = config('nis.frontend_url');
        if (Str::startsWith($target, '/')) {
            return redirect($target);
        }

        if ($target === $frontend || Str::startsWith($target, $frontend.'/')) {
            return redirect()->away($target);
        }

        return redirect('/');
    }
}
