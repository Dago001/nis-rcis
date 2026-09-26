<?php

namespace App\Support;

use App\Models\OAuthClient;
use Illuminate\Support\Str;

/**
 * Factory for the NIS-RCIS OAuth2 clients.
 *
 * Returns [client, plainSecret]. The secret is hashed at rest and can only
 * be shown once, at creation.
 */
class OAuthClients
{
    /** @return array{0: OAuthClient, 1: string} */
    public static function staffConsole(string $redirectUri): array
    {
        return self::make('NIS-RCIS Staff Console', 'users', ['staff'], [$redirectUri], ['authorization_code', 'refresh_token'], firstParty: true);
    }

    /** @return array{0: OAuthClient, 1: string} */
    public static function applicantPortal(string $redirectUri): array
    {
        return self::make('NIS-RCIS Applicant Portal', 'applicants', ['applicant'], [$redirectUri], ['authorization_code', 'refresh_token'], firstParty: true);
    }

    /** Machine-to-machine client for a partner agency (card verification only). */
    public static function partner(string $name): array
    {
        return self::make($name, null, ['cards:verify'], [], ['client_credentials'], firstParty: false);
    }

    /** @return array{0: OAuthClient, 1: string} */
    private static function make(string $name, ?string $provider, array $scopes, array $redirectUris, array $grantTypes, bool $firstParty): array
    {
        $secret = Str::random(48);

        $client = OAuthClient::query()->forceCreate([
            'name' => $name,
            'secret' => $secret,
            'provider' => $provider,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
            'scopes' => $scopes,
            'first_party' => $firstParty,
            'revoked' => false,
        ]);

        return [$client, $secret];
    }
}
