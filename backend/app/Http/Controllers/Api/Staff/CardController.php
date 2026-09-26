<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\CardResource;
use App\Models\ApprovalRequest;
use App\Models\ResidenceCard;
use App\Services\CardIssuance;
use App\Services\DocumentStorage;
use App\Services\TwoPersonRule;
use App\Support\ApplicationRules;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Residence card register and lifecycle
 * (legacy view-cards.php, card-details.php, edit-card.php, renew-card.php).
 */
class CardController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'watchlisted' => ['nullable', 'boolean'],
            'expired' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = ResidenceCard::query()->latest('id');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['watchlisted'])) {
            $query->where('is_watchlisted', (bool) $data['watchlisted']);
        }
        if (! empty($data['expired'])) {
            $query->where('expires_on', '<', today());
        }
        if (! empty($data['search'])) {
            $term = strtoupper(trim($data['search']));
            $query->where(fn ($q) => $q->where('card_number', $term)
                ->orWhere('booklet_number', $term)
                ->orWhere('passport_number', $term)
                ->orWhere('surname', 'ilike', Like::contains($term))
                ->orWhere('forenames', 'ilike', Like::contains($term)));
        }

        return CardResource::collection($query->paginate(25));
    }

    public function show(int $id, DocumentStorage $storage): JsonResponse
    {
        $card = ResidenceCard::with(['renewals', 'application'])->findOrFail($id);

        return response()->json([
            'data' => new CardResource($card),
            'photo_url' => $storage->temporaryUrlForPath($card->photo_path),
            'signature_url' => $storage->temporaryUrlForPath($card->signature_path),
        ]);
    }

    /**
     * Everything needed to print the card, booklet and certificate,
     * including the QR verification URL.
     */
    public function print(int $id, DocumentStorage $storage): JsonResponse
    {
        $card = ResidenceCard::with('renewals')->findOrFail($id);
        abort_unless($card->status->isActive(), 422, 'Only an approved and issued card can be printed.');

        return response()->json([
            'data' => new CardResource($card),
            'photo_url' => $storage->temporaryUrlForPath($card->photo_path),
            'signature_url' => $storage->temporaryUrlForPath($card->signature_path),
            'verification_url' => config('nis.frontend_url').'/verify?token='.$card->verification_token,
        ]);
    }

    public function update(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $request->merge(ApplicationRules::normalise($request->all()));
        $rules = Arr::except(ApplicationRules::particulars(), ['date_of_birth', 'passport_expiry']);
        $rules = array_map(fn ($r) => array_map(fn ($x) => $x === 'required' ? 'sometimes' : $x, $r), $rules);
        $rules += [
            'date_of_birth' => ['sometimes', 'date'],
            'passport_expiry' => ['sometimes', 'nullable', 'date'],
            'statutory_protocol' => ['sometimes', 'string', 'max:150'],
            'postage_stamp_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];

        $data = $request->validate([...$rules, 'change_reason' => ['required', 'string', 'max:255']]);
        $reason = $data['change_reason'];
        unset($data['change_reason']);
        abort_if($data === [], 422, 'No changes to request.');
        $card = ResidenceCard::findOrFail($id);
        abort_unless(in_array($card->status, [CardStatus::Approved, CardStatus::Queried], true), 422, 'Only cards that are not yet issued can be corrected.');

        return $this->pending($rule->request(ApprovalRequest::UPDATE, $card, $request->user(), $reason, $data));
    }

    public function decide(Request $request, int $id, CardIssuance $issuance): CardResource
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVE', 'QUERY'])],
            'notes' => ['required_if:decision,QUERY', 'nullable', 'string', 'max:255'],
        ]);

        $card = ResidenceCard::findOrFail($id);
        $card = $data['decision'] === 'APPROVE'
            ? $issuance->approve($card, $request->user())
            : $issuance->query($card, $request->user(), $data['notes']);

        return new CardResource($card);
    }

    public function readyForCollection(Request $request, int $id, CardIssuance $issuance): ApplicationResource
    {
        $application = $issuance->markReadyForCollection(ResidenceCard::with('application')->findOrFail($id), $request->user());

        return new ApplicationResource($application->load(['enrollmentCenter', 'card', 'statusHistory']));
    }

    public function renew(Request $request, int $id, CardIssuance $issuance): CardResource
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after:from_date'],
            'renewed_at' => ['nullable', 'string', 'max:150'],
            'fee_paid_naira' => ['nullable', 'numeric', 'min:0'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        return new CardResource($issuance->renew(ResidenceCard::findOrFail($id), $request->user(), $data)->load('renewals'));
    }

    /** Two-person rule: creates a request a second officer must approve. */
    public function revoke(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $card = ResidenceCard::findOrFail($id);
        abort_if($card->status === CardStatus::Revoked, 422, 'This card is already revoked.');
        $approval = $rule->request(ApprovalRequest::REVOKE, $card, $request->user(), $data['reason']);

        return $this->pending($approval);
    }

    /** Two-person rule: creates a request a second Super Administrator must approve. */
    public function reinstate(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $card = ResidenceCard::findOrFail($id);
        abort_unless($card->status === CardStatus::Revoked, 422, 'Only a revoked card can be reinstated.');
        $approval = $rule->request(ApprovalRequest::REINSTATE, $card, $request->user(), $data['reason']);

        return $this->pending($approval);
    }

    private function pending(ApprovalRequest $approval): JsonResponse
    {
        return response()->json([
            'message' => "{$approval->label()} requested. A second officer must approve it before it takes effect (two-person rule).",
            'approval_request_id' => $approval->id,
            'status' => 'PENDING',
        ], 202);
    }

    public function watchlist(Request $request, int $id, CardIssuance $issuance): CardResource
    {
        $data = $request->validate([
            'watchlisted' => ['required', 'boolean'],
            'reason' => ['required_if:watchlisted,true', 'nullable', 'string', 'max:255'],
        ]);

        return new CardResource($issuance->setWatchlist(ResidenceCard::findOrFail($id), $request->user(), (bool) $data['watchlisted'], $data['reason'] ?? null));
    }

    /** The holder found the card: withdraw the lost/stolen report. */
    public function clearLostReport(Request $request, int $id, CardIssuance $issuance): CardResource
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:255']]);

        return new CardResource($issuance->clearLostReport(ResidenceCard::findOrFail($id), $request->user(), $data['notes']));
    }
}
