<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * OAuth2 client with two NIS-specific rules:
 *
 *  - `scopes`: the only scopes this client may ever request (enforced by
 *    Passport's ScopeRepository through Client::hasScope()).
 *  - `first_party`: our own Next.js portals skip the consent screen.
 *    Third-party clients never do.
 */
class OAuthClient extends Client
{
    protected function casts(): array
    {
        return ['first_party' => 'boolean'];
    }

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return (bool) $this->first_party;
    }
}
