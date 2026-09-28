<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff tokens stop working the moment an account is deactivated, and
 * cannot be used before a temporary password is replaced.
 */
class EnsureActiveStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 403, 'This staff account is deactivated.');
        abort_if($user->must_change_password, 403, 'You must change your temporary password before continuing.');

        return $next($request);
    }
}
