<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\ResidenceCard;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;

/**
 * Machine-to-machine card verification for partner agencies
 * (OAuth2 client_credentials, scope cards:verify).
 */
class PartnerController
{
    public function verifyCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_number' => ['required', 'string', 'max:20'],
            'passport_number' => ['required', 'string', 'max:20'],
        ]);

        $card = ResidenceCard::where('card_number', trim($data['card_number']))
            ->where('passport_number', strtoupper(trim($data['passport_number'])))
            ->first();

        $client = Passport::client()->newQuery()->find($this->clientId($request));
        Audit::log('PARTNER_CARD_VERIFICATION', 'Card verification by partner '.($client?->name ?? 'unknown'), $card,
            ['card_number' => $data['card_number'], 'found' => (bool) $card], actorLabel: 'PARTNER: '.($client?->name ?? 'unknown'));

        abort_unless($card, 404, 'No residence card matches the details supplied.');

        return response()->json([
            'status' => $card->verificationStatus(),
            'card_number' => $card->card_number,
            'booklet_number' => $card->booklet_number,
            'holder' => "{$card->surname}, {$card->forenames}",
            'nationality' => $card->nationality,
            'date_of_birth' => $card->date_of_birth?->toDateString(),
            'sex' => $card->sex,
            'issued_on' => $card->issued_on?->toDateString(),
            'expires_on' => $card->expires_on?->toDateString(),
            'is_watchlisted' => $card->is_watchlisted,
            'watchlist_reason' => $card->is_watchlisted ? $card->watchlist_reason : null,
            'revocation_reason' => $card->revocation_reason,
        ]);
    }

    /**
     * The token was already validated by the `client:cards:verify` middleware;
     * read its audience (the client id) for the audit trail.
     */
    private function clientId(Request $request): ?string
    {
        $jwt = $request->bearerToken();
        if (! $jwt || substr_count($jwt, '.') !== 2) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);

        return $payload['aud'] ?? null;
    }
}
