<?php

namespace App\Http\Controllers\OAuth;

use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;

/**
 * Revoke the calling access token and its refresh tokens (used on logout).
 */
class RevokeTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenId = $user?->token()?->oauth_access_token_id;

        if ($tokenId) {
            Passport::token()->newQuery()->whereKey($tokenId)->update(['revoked' => true]);
            Passport::refreshToken()->newQuery()->where('access_token_id', $tokenId)->update(['revoked' => true]);
            Audit::log('LOGOUT', 'Signed out and revoked OAuth tokens', actor: $user);
        }

        return response()->json(['revoked' => (bool) $tokenId]);
    }
}
