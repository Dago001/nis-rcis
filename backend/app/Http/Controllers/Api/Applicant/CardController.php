<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Applicant;
use App\Models\ResidenceCard;
use App\Notifications\CardReportedLost;
use App\Services\CardIssuance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The holder's own residence cards, and reporting one lost or stolen.
 */
class CardController
{
    public function index(Request $request): JsonResponse
    {
        $cards = ResidenceCard::where('applicant_id', $this->applicant($request)->id)->latest('id')->get();

        return response()->json(['data' => $cards->map(fn (ResidenceCard $c) => $this->present($c))]);
    }

    public function reportLost(Request $request, int $id, CardIssuance $issuance): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['LOST', 'STOLEN'])],
            'details' => ['required', 'string', 'min:10', 'max:1000'],
            'police_report_number' => ['required_if:type,STOLEN', 'nullable', 'string', 'max:60'],
        ]);
        $applicant = $this->applicant($request);
        $card = ResidenceCard::where('applicant_id', $applicant->id)->findOrFail($id);

        $card = $issuance->reportLost($card, $applicant, $data['type'], $data['details'], $data['police_report_number'] ?? null);
        $applicant->notify(new CardReportedLost($card));

        return response()->json([
            'message' => 'Your card has been reported '.strtolower($data['type']).'. It now shows as invalid to anyone who checks it. You can apply for a replacement.',
            'data' => $this->present($card),
        ]);
    }

    private function present(ResidenceCard $c): array
    {
        return [
            'id' => $c->id,
            'card_number' => $c->card_number,
            'holder' => "{$c->surname}, {$c->forenames}",
            'status' => $c->status->value,
            'verification_status' => $c->verificationStatus(),
            'issued_on' => $c->issued_on?->toDateString(),
            'expires_on' => $c->expires_on?->toDateString(),
            'reported_lost_at' => $c->reported_lost_at?->toIso8601String(),
            'lost_report_type' => $c->lost_report_type,
            'can_report' => $c->reported_lost_at === null && ($c->status->isActive() || $c->status->value === 'APPROVED'),
            'can_replace' => $c->reported_lost_at !== null && $c->status->value !== 'REVOKED' && ! $c->is_watchlisted,
        ];
    }

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}
