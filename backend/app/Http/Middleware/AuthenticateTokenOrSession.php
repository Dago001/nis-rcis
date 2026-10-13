<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTokenOrSession
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $hasBearerToken = ! empty($request->header('Authorization'))
            || ! empty($request->server->get('HTTP_AUTHORIZATION'))
            || ! empty($request->bearerToken());
        $tokenGuard = $scope === 'staff' ? 'api' : 'applicant-api';
        $sessionGuard = $scope === 'staff' ? 'staff' : 'applicant';

        $tokenUser = auth($tokenGuard)->user();

        if ($hasBearerToken || $tokenUser) {
            if (! $tokenUser) {
                abort(401, 'Unauthenticated.');
            }
            auth()->shouldUse($tokenGuard);
            $request->setUserResolver(fn () => $tokenUser);

            return $next($request);
        }

        $sessionUser = auth($sessionGuard)->user();
        if (! $sessionUser) {
            abort(401, 'Unauthenticated.');
        }
        auth()->shouldUse($sessionGuard);
        $request->setUserResolver(fn () => $sessionUser);

        return $next($request);
    }
}
