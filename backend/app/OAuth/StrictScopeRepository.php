<?php

namespace App\OAuth;

use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Passport silently drops scopes a client is not allowed to request. For
 * NIS-RCIS a client asking for a scope outside its allow-list (e.g. the
 * applicant portal asking for "staff") is refused with invalid_scope.
 */
class StrictScopeRepository extends ScopeRepository
{
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $allowed = parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);

        $requested = array_map(fn (ScopeEntityInterface $s) => $s->getIdentifier(), $scopes);
        $granted = array_map(fn (ScopeEntityInterface $s) => $s->getIdentifier(), $allowed);

        if ($denied = array_values(array_diff($requested, $granted))) {
            throw OAuthServerException::invalidScope(implode(' ', $denied));
        }

        if ($granted === []) {
            throw OAuthServerException::invalidScope('');
        }

        return $allowed;
    }
}
