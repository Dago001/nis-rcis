<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every API response and OAuth sign-in page.
 *
 * JSON responses get a "deny everything" Content-Security-Policy; the Blade
 * sign-in pages may only load their own images and inline stylesheet, may
 * not run any script. (form-action is left open: Chrome applies it to the
 * redirect after the OAuth approve form, which goes to the client's origin.)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        $isHtml = str_contains((string) $headers->get('Content-Type'), 'text/html');

        $headers->set('Content-Security-Policy', $isHtml
            ? "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'"
            : "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $headers->remove('X-Powered-By');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
