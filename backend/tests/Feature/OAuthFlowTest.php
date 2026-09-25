<?php

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\User;
use App\Support\OAuthClients;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Drives the real OAuth2 authorization-code + PKCE flow end to end, exactly
 * as the Next.js BFF does it.
 */
function pkce(): array
{
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function authorizeUrl(string $clientId, string $redirect, string $scope, string $challenge, string $state = 'xyz'): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);
}

function codeFrom(string $location): string
{
    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('code');

    return $query['code'];
}

it('runs the applicant authorization code + PKCE flow and calls the API', function () {
    $redirect = 'http://portal.test/api/auth/callback/applicant';
    [$client, $secret] = OAuthClients::applicantPortal($redirect);
    $applicant = Applicant::factory()->create(['email' => 'ada@example.com']);
    [$verifier, $challenge] = pkce();
    $url = authorizeUrl($client->id, $redirect, 'applicant', $challenge);

    // 1. Guest is sent to the APPLICANT login page (not the staff one).
    $this->get($url)->assertRedirect(route('login.applicant'));

    // 2. Sign in -> back to the authorization request.
    $this->post('/login/applicant', ['identifier' => 'ada@example.com', 'password' => 'Applicant-Pass-123'])
        ->assertRedirectContains('/oauth/authorize');

    // 3. First-party client: no consent screen, code issued immediately.
    $response = $this->get($url)->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith($redirect)->toContain('state=xyz');
    $code = codeFrom($response->headers->get('Location'));

    // 4. Server-side code exchange (client secret + PKCE verifier).
    $tokens = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $secret,
        'redirect_uri' => $redirect,
        'code_verifier' => $verifier,
        'code' => $code,
    ])->assertOk()->json();

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token', 'expires_in']);

    // 5. Bearer token works on the applicant API ...
    $this->withToken($tokens['access_token'])->getJson('/api/v1/applicant/me')
        ->assertOk()->assertJsonPath('email', 'ada@example.com');

    // ... and never on the staff API.
    $this->app['auth']->forgetGuards();
    $this->withToken($tokens['access_token'])->getJson('/api/v1/staff/me')->assertUnauthorized();

    // 6. Refresh token rotation.
    $this->app['auth']->forgetGuards();
    $refreshed = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->id,
        'client_secret' => $secret,
        'scope' => 'applicant',
    ])->assertOk()->json();
    expect($refreshed['access_token'])->not->toBe($tokens['access_token']);

    // 7. Logout revokes the token.
    $this->app['auth']->forgetGuards();
    $this->withToken($refreshed['access_token'])->postJson('/api/v1/applicant/oauth/revoke')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($refreshed['access_token'])->getJson('/api/v1/applicant/me')->assertUnauthorized();
});

it('runs the staff flow, forcing a temporary password change first', function () {
    $redirect = 'http://portal.test/api/auth/callback/staff';
    [$client, $secret] = OAuthClients::staffConsole($redirect);
    User::factory()->role(StaffRole::ApprovingOfficer)->create([
        'service_number' => '24820', 'must_change_password' => true, 'password' => 'Temporary-Pass-1',
    ]);
    [$verifier, $challenge] = pkce();
    $url = authorizeUrl($client->id, $redirect, 'staff', $challenge);

    $this->get($url)->assertRedirect(route('login.staff'));
    $this->post('/login/staff', ['identifier' => '24820', 'password' => 'Temporary-Pass-1'])
        ->assertRedirect(route('password.change'));

    // Cannot obtain a code until the password is changed.
    $this->get($url)->assertRedirect(route('password.change'));

    $this->post('/password/change', [
        'current_password' => 'Temporary-Pass-1',
        'password' => 'N3w-Strong-Passw0rd!',
        'password_confirmation' => 'N3w-Strong-Passw0rd!',
    ])->assertRedirectContains('/oauth/authorize');

    $code = codeFrom($this->get($url)->headers->get('Location'));

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->id, 'client_secret' => $secret,
        'redirect_uri' => $redirect, 'code_verifier' => $verifier, 'code' => $code,
    ])->assertOk()->json('access_token');

    $this->withToken($token)->getJson('/api/v1/staff/me')->assertOk()->assertJsonPath('role', 'ApprovingOfficer');
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'gd required');

it('rejects scopes a client is not allowed to request', function () {
    $redirect = 'http://portal.test/api/auth/callback/applicant';
    [$client] = OAuthClients::applicantPortal($redirect);
    Applicant::factory()->create(['email' => 'ada@example.com']);
    [, $challenge] = pkce();

    $this->post('/login/applicant', ['identifier' => 'ada@example.com', 'password' => 'Applicant-Pass-123']);

    $response = $this->get(authorizeUrl($client->id, $redirect, 'staff', $challenge));
    expect($response->headers->get('Location'))->toContain('error=invalid_scope');
});

it('does not let an applicant session authorize the staff client', function () {
    $redirect = 'http://portal.test/api/auth/callback/staff';
    [$client] = OAuthClients::staffConsole($redirect);
    Applicant::factory()->create(['email' => 'ada@example.com']);
    [, $challenge] = pkce();

    $this->post('/login/applicant', ['identifier' => 'ada@example.com', 'password' => 'Applicant-Pass-123']);

    $this->get(authorizeUrl($client->id, $redirect, 'staff', $challenge))->assertRedirect(route('login.staff'));
});

it('blocks unverified applicants and deactivated staff at login', function () {
    Applicant::factory()->unverified()->create(['email' => 'new@example.com']);
    $this->post('/login/applicant', ['identifier' => 'new@example.com', 'password' => 'Applicant-Pass-123'])
        ->assertSessionHasErrors('identifier');

    User::factory()->create(['service_number' => '11111', 'is_active' => false]);
    $this->post('/login/staff', ['identifier' => '11111', 'password' => 'Correct-Horse-9-Battery!'])
        ->assertSessionHasErrors('identifier');
});

it('issues client_credentials tokens to partners for card verification only', function () {
    [$client, $secret] = OAuthClients::partner('Nigeria Police Force');

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->id, 'client_secret' => $secret, 'scope' => 'cards:verify',
    ])->assertOk()->json('access_token');

    $this->withToken($token)->getJson('/api/v1/partner/cards/verify?card_number=1&passport_number=A1234567')
        ->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/staff/me')->assertUnauthorized();

    $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->id, 'client_secret' => $secret, 'scope' => 'staff',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
});

it('refuses the password grant', function () {
    [$client, $secret] = OAuthClients::applicantPortal('http://portal.test/cb');
    Applicant::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/oauth/token', [
        'grant_type' => 'password', 'client_id' => $client->id, 'client_secret' => $secret,
        'username' => 'ada@example.com', 'password' => 'Applicant-Pass-123', 'scope' => 'applicant',
    ])->assertStatus(400);
});

it('serves the token endpoint without session/CSRF middleware (server-to-server)', function () {
    $middleware = Route::getRoutes()->getByName('passport.token')->gatherMiddleware();

    expect($middleware)->not->toContain('web');
});
