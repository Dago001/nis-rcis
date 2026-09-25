<?php

namespace App\Http\Middleware;

use App\Enums\StaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:ApprovingOfficer,IssuingOfficer')
 * SuperAdmin always passes (legacy requireRole() behaviour).
 */
class EnsureStaffRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $r) => StaffRole::from($r), $roles);

        abort_unless($request->user()?->hasRole(...$allowed), 403, 'You do not have the clearance required for this operation.');

        return $next($request);
    }
}
