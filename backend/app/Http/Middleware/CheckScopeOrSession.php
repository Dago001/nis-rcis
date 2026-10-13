<?php

namespace App\Http\Middleware;

use App\Models\Applicant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Passport token scopes or matches active session user.
 * Allows both session authentication (Inertia/SPA direct calls)
 * and OAuth2 bearer tokens (external API integrations).
 */
class CheckScopeOrSession
{
    public function __construct(
        protected CheckToken $passportCheck,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        // If authenticated via session guard without a bearer token
        if ($user && ! $request->bearerToken() && ! method_exists($user, 'currentAccessToken') || ($user && ! $request->bearerToken() && ! $user->currentAccessToken())) {
            foreach ($scopes as $scope) {
                if ($scope === 'staff' && ! ($user instanceof User)) {
                    abort(403, 'Invalid identity for staff operation.');
                }
                if ($scope === 'applicant' && ! ($user instanceof Applicant)) {
                    abort(403, 'Invalid identity for applicant operation.');
                }
            }

            return $next($request);
        }

        // Delegate to Passport token check for bearer token requests
        return $this->passportCheck->handle($request, $next, ...$scopes);
    }
}
