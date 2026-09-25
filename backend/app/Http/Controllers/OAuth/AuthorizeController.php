<?php

namespace App\Http\Controllers\OAuth;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth2 authorization endpoint (GET /oauth/authorize).
 *
 * Passport's controller authenticates the resource owner with a single
 * session guard. NIS-RCIS has two populations, so the guard is chosen from
 * the requesting client's provider:
 *
 *   provider "applicants" -> "applicant" guard -> /login/applicant
 *   provider "users"      -> "staff" guard     -> /login/staff
 */
class AuthorizeController extends AuthorizationController
{
    public function __construct(AuthorizationServer $server, ClientRepository $clients)
    {
        parent::__construct($server, Auth::guard('staff'), $clients);
    }

    public function authorize(
        ServerRequestInterface $psrRequest,
        Request $request,
        ResponseInterface $psrResponse,
        AuthorizationViewResponse $viewResponse
    ): Response|AuthorizationViewResponse {
        $this->guard = self::guardForClient($this->clients, (string) $request->query('client_id'));

        if ($error = $this->rejectDisallowedScopes($request)) {
            return $error;
        }

        $user = $this->guard->user();

        if ($user instanceof User && ! $user->is_active) {
            $this->guard->logout();
        } elseif ($user instanceof User && $user->must_change_password) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('password.change');
        }

        return parent::authorize($psrRequest, $request, $psrResponse, $viewResponse);
    }

    /**
     * Refuse up front (before login) any scope outside the client's allow-list.
     * Token issuance enforces the same rule (StrictScopeRepository).
     */
    private function rejectDisallowedScopes(Request $request): ?Response
    {
        $client = $this->clients->findActive((string) $request->query('client_id'));
        if (! $client || ! isset($client->getAttributes()['scopes'])) {
            return null; // unknown client: Passport returns invalid_client
        }

        $requested = array_filter(explode(' ', (string) $request->query('scope')));
        $denied = array_values(array_filter($requested, fn (string $s) => ! $client->hasScope($s)));

        if ($requested !== [] && $denied === []) {
            return null;
        }

        $redirect = (string) $request->query('redirect_uri');
        abort_unless(in_array($redirect, $client->redirect_uris, true), 400, 'invalid_scope');

        return redirect()->away($redirect.(str_contains($redirect, '?') ? '&' : '?').http_build_query([
            'error' => 'invalid_scope',
            'error_description' => 'The requested scope is not allowed for this client.',
            'state' => $request->query('state'),
        ]));
    }

    public static function guardForClient(ClientRepository $clients, string $clientId): StatefulGuard
    {
        $client = $clientId !== '' ? $clients->find($clientId) : null;

        return Auth::guard($client?->provider === 'applicants' ? 'applicant' : 'staff');
    }

    /**
     * Send guests to the login page for the correct population, then back
     * to this exact authorization request.
     */
    protected function promptForLogin(Request $request): never
    {
        $request->session()->put('promptedForLogin', true);
        $request->session()->put('url.intended', $request->fullUrl());

        $route = $this->guard === Auth::guard('applicant') ? 'login.applicant' : 'login.staff';

        throw new HttpResponseException(redirect()->route($route));
    }
}
