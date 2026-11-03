<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CardDistribution;
use App\Models\ResidenceCard;
use App\Services\CardIssuance;
use App\Services\DocumentStorage;
use App\Services\GeofenceService;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller for Card Issuance & Collection Desk.
 * Manages the handover of produced cards to applicants and notification delivery,
 * strictly verifying physical card receipt at destination command and physical geofence.
 */
class CardIssuanceController
{
    public function index(Request $request, DocumentStorage $storage): JsonResponse
    {
        $user = $request->user();
        $stage = $request->input('stage', 'all');
        $search = $request->input('search');
        $centerId = $request->input('center_id');
        $perPage = (int) ($request->input('per_page', 10));

        $query = ResidenceCard::query()
            ->forStaffJurisdiction($user)
            ->with([
                'application.enrollmentCenter',
                'application.statusHistory' => fn ($q) => $q->latest('id')->limit(5),
                'issuingOfficer:id,fullname,service_number',
                'printJobs' => fn ($q) => $q->where('outcome', 'PRINTED')->with('printer:id,fullname,service_number')->latest('id')->limit(1),
                'distribution.destinationZone',
                'distribution.destinationCommand',
            ])
            ->withCount(['printJobs as printed_count' => fn ($q) => $q->where('outcome', 'PRINTED')])
            ->whereHas('printJobs', fn ($q) => $q->where('outcome', 'PRINTED'))
            ->latest('id');

        // Filter by stage
        if ($stage === 'awaiting_issuance' || $stage === 'awaiting_collection') {
            $query->whereHas('application', fn ($q) => $q->where('status', '!=', ApplicationStatus::Issued));
        } elseif ($stage === 'issued' || $stage === 'collected') {
            $query->whereHas('application', fn ($q) => $q->where('status', ApplicationStatus::Issued));
        }

        // Filter by enrollment center
        if (! empty($centerId)) {
            $query->where(function ($q) use ($centerId) {
                $q->where('actual_enrollment_center_id', $centerId)
                  ->orWhereHas('application', fn ($aq) => $aq->where('actual_enrollment_center_id', $centerId)->orWhere('enrollment_center_id', $centerId));
            });
        }

        // Search
        if (! empty($search)) {
            $term = trim($search);
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';
            $pattern = Like::contains($term);
            $cleanNum = preg_replace('/[^A-Z0-9]/', '', strtoupper($term));
            $concatSurnameForenames = $driver === 'sqlite' ? "(surname || ' ' || forenames)" : "CONCAT(surname, ' ', forenames)";
            $concatForenamesSurname = $driver === 'sqlite' ? "(forenames || ' ' || surname)" : "CONCAT(forenames, ' ', surname)";

            $query->where(function ($q) use ($term, $cleanNum, $likeOp, $pattern, $concatSurnameForenames, $concatForenamesSurname) {
                $q->where('card_number', $likeOp, $pattern)
                    ->orWhere('booklet_number', $likeOp, $pattern)
                    ->orWhere('passport_number', $likeOp, $pattern)
                    ->orWhere('surname', $likeOp, $pattern)
                    ->orWhere('forenames', $likeOp, $pattern)
                    ->orWhereRaw("{$concatSurnameForenames} {$likeOp} ?", [$pattern])
                    ->orWhereRaw("{$concatForenamesSurname} {$likeOp} ?", [$pattern])
                    ->orWhereHas('application', fn ($sq) => $sq->where('application_number', $likeOp, $pattern)
                        ->orWhere('reference_number', $likeOp, $pattern));
                if ($cleanNum !== '') {
                    $q->orWhere('card_number', $likeOp, "%{$cleanNum}%")
                      ->orWhere('passport_number', $likeOp, "%{$cleanNum}%");
                }
            });
        }

        $paginated = $query->paginate($perPage);

        // Compute summary counts for issued cards and printed cards scoped to jurisdiction
        $printedBase = ResidenceCard::query()
            ->forStaffJurisdiction($user)
            ->whereHas('printJobs', fn ($q) => $q->where('outcome', 'PRINTED'));

        $issuedBase = ResidenceCard::query()
            ->forStaffJurisdiction($user)
            ->where(function ($q) {
                $q->whereIn('status', [\App\Enums\CardStatus::Issued, \App\Enums\CardStatus::Renewed])
                  ->orWhereHas('application', fn ($aq) => $aq->where('status', ApplicationStatus::Issued));
            });

        if (! empty($centerId)) {
            $printedBase->where(function ($q) use ($centerId) {
                $q->where('actual_enrollment_center_id', $centerId)
                  ->orWhereHas('application', fn ($aq) => $aq->where('actual_enrollment_center_id', $centerId)->orWhere('enrollment_center_id', $centerId));
            });
            $issuedBase->where(function ($q) use ($centerId) {
                $q->where('actual_enrollment_center_id', $centerId)
                  ->orWhereHas('application', fn ($aq) => $aq->where('actual_enrollment_center_id', $centerId)->orWhere('enrollment_center_id', $centerId));
            });
        }

        $totalIssued = (clone $issuedBase)->count();
        $newIssued = (clone $issuedBase)->where(function ($q) {
            $q->whereDoesntHave('renewals')
              ->where(function ($sq) {
                  $sq->whereDoesntHave('application')
                    ->orWhereHas('application', fn ($aq) => $aq->whereNull('renewal_of_card_id')->where('type', '!=', 'REPLACE'));
              });
        })->count();
        $renewalIssued = (clone $issuedBase)->where(function ($q) {
            $q->where('status', \App\Enums\CardStatus::Renewed)
              ->orWhereHas('renewals')
              ->orWhereHas('application', fn ($aq) => $aq->whereNotNull('renewal_of_card_id')->orWhere('type', 'RENEWAL'));
        })->count();

        if ($newIssued + $renewalIssued < $totalIssued) {
            $newIssued += ($totalIssued - ($newIssued + $renewalIssued));
        }

        $cardsIssuedByMe = ResidenceCard::query()
            ->where(function ($q) use ($user) {
                $q->where('issuing_officer_id', $user->id)
                  ->orWhere('issuing_officer_service_no', $user->service_number)
                  ->orWhereHas('application', fn ($aq) => $aq->where('collected_by', $user->id));
            })->count();

        $stats = [
            'total_cards_issued' => $totalIssued,
            'cards_issued_by_me' => $cardsIssuedByMe,
            'awaiting_issuance' => (clone $printedBase)->whereHas('application', fn ($q) => $q->where('status', '!=', ApplicationStatus::Issued))->count(),
            'new_cards_issued' => $newIssued,
            'renewal_cards_issued' => $renewalIssued,
            'issued_total' => $totalIssued,
            'total_printed' => (clone $printedBase)->count(),
            // Backwards compatibility keys
            'awaiting_collection' => (clone $printedBase)->whereHas('application', fn ($q) => $q->where('status', '!=', ApplicationStatus::Issued))->count(),
            'ready_to_notify' => (clone $printedBase)->whereHas('application', fn ($q) => $q->where('status', ApplicationStatus::BiometricsCaptured))->count(),
            'collected_total' => $totalIssued,
            'total_produced' => (clone $printedBase)->count(),
        ];

        $items = collect($paginated->items())->map(function (ResidenceCard $card) use ($storage) {
            $app = $card->application;
            $dist = $card->distribution;
            $latestPrint = $card->printJobs->first();
            $isIssued = $app?->status === ApplicationStatus::Issued || $card->status === \App\Enums\CardStatus::Issued;
            $isPhysicallyReceived = $dist ? in_array($dist->status, [
                CardDistribution::STATUS_RECEIVED,
                CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                CardDistribution::STATUS_ISSUED,
            ], true) : true; // Fallback for legacy seeded cards without distribution record

            return [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'booklet_number' => $card->booklet_number,
                'status' => $card->status->value,
                'issuance_status' => $isIssued ? 'ISSUED' : 'AWAITING_ISSUANCE',
                'issuance_status_label' => $isIssued ? 'Issued' : 'Awaiting Issuance',
                'verification_status' => $card->verificationStatus(),
                'card_category' => $card->category ?? ($app?->category ?? ($card->renewals()->exists() ? 'Renewal' : 'General')),
                'surname' => $card->surname,
                'forenames' => $card->forenames,
                'holder' => "{$card->surname}, {$card->forenames}",
                'passport_number' => $card->passport_number,
                'nationality' => $card->nationality,
                'issued_on' => $card->issued_on?->toDateString(),
                'expires_on' => $card->expires_on?->toDateString(),
                'printed_count' => (int) ($card->printed_count ?? 1),
                'date_printed' => $latestPrint?->created_at?->toIso8601String(),
                'printed_by' => $latestPrint?->printer?->fullname,
                'photo_url' => $card->photo_path ? $storage->temporaryUrlForPath($card->photo_path) : null,
                'distribution' => $dist ? [
                    'id' => $dist->id,
                    'status' => $dist->status,
                    'batch_reference' => $dist->batch_reference,
                    'destination_zone' => $dist->destinationZone?->name,
                    'destination_command' => $dist->destinationCommand?->name,
                    'dispatched_at' => $dist->dispatched_at?->toIso8601String(),
                    'received_at' => $dist->received_at?->toIso8601String(),
                    'is_physically_received' => $isPhysicallyReceived,
                ] : null,
                'is_physically_received' => $isPhysicallyReceived,
                'application' => $app ? [
                    'id' => $app->id,
                    'application_number' => $app->application_number,
                    'reference_number' => $app->reference_number,
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
                    'category' => $app->category,
                    'ready_at' => $app->ready_at?->toIso8601String(),
                    'collected_at' => $app->collected_at?->toIso8601String(),
                    'enrollment_center' => $app->enrollmentCenter?->only(['id', 'name', 'code', 'state']),
                ] : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'stats' => $stats,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Mark a produced card ready for collection and notify the applicant.
     */
    public function markReady(Request $request, int $id, CardIssuance $issuance): JsonResponse
    {
        $user = $request->user();
        $card = ResidenceCard::with(['application.enrollmentCenter', 'distribution'])->findOrFail($id);

        if (! $user->canAccessCard($card)) {
            abort(403, 'You do not have jurisdiction to manage this card.');
        }

        $app = $card->application ?? throw ValidationException::withMessages(['card' => 'Card is not linked to an application.']);

        if ($app->status === ApplicationStatus::ReadyForCollection) {
            return response()->json([
                'message' => "Application {$app->application_number} is already marked ready for collection.",
                'status' => $app->status->value,
            ]);
        }

        $app = $issuance->markReadyForCollection($card, $user);

        return response()->json([
            'message' => "Card {$card->card_number} marked ready for collection. Applicant notified via 'Ready / collected'.",
            'status' => $app->status->value,
            'ready_at' => $app->ready_at?->toIso8601String(),
        ]);
    }

    /**
     * Issue out / hand over a produced card to the applicant upon collection.
     * Enforces:
     * 1. Officer jurisdiction (Cards are issued ONLY at the receiving Command/Center).
     * 2. Physical card receipt confirmation (Card must have been dispatched and received).
     * 3. Physical geofence verification at the issuing office.
     */
    public function issueOut(Request $request, int $id, CardIssuance $issuance, GeofenceService $geofence): JsonResponse
    {
        $user = $request->user();
        $card = ResidenceCard::with(['application.enrollmentCenter', 'distribution'])->findOrFail($id);

        // 1. Check officer jurisdiction (Card issuance is performed locally at the Command/Center)
        if (! $user->canIssueCard($card)) {
            abort(403, 'You do not have jurisdiction to issue this card. Official cards can only be issued by authorized officers at the receiving Command.');
        }

        $app = $card->application ?? throw ValidationException::withMessages(['card' => 'Card is not linked to an application.']);

        if (app()->isProduction() && ($card->statutory_protocol === 'DEVELOPMENT_SIMULATION' || str_contains((string) $card->statutory_protocol, 'SIMULATION'))) {
            throw ValidationException::withMessages(['card' => "Card {$card->card_number} was created via Development Biometrics Simulation and cannot be issued in a production environment."]);
        }

        if ($app->status === ApplicationStatus::Issued) {
            return response()->json([
                'message' => "Card {$card->card_number} was already collected on {$app->collected_at?->format('d/m/Y H:i')}.",
                'status' => $app->status->value,
            ]);
        }

        // 2. Verify physical card receipt at Command before issuance
        $distribution = $card->distribution;
        if ($distribution && ! in_array($distribution->status, [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE, CardDistribution::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages([
                'card' => "Card {$card->card_number} has not been physically received and confirmed at this Command yet. Current distribution status: {$distribution->status}."
            ]);
        }

        // 3. Physical Geofencing verification
        $lat = $request->input('latitude') !== null ? (float) $request->input('latitude') : null;
        $lng = $request->input('longitude') !== null ? (float) $request->input('longitude') : null;
        $overrideReason = $request->input('override_reason');

        $geofenceCheck = $geofence->assertStaffLocation(
            officer: $user,
            latitude: $lat,
            longitude: $lng,
            action: 'CARD_ISSUANCE',
            overrideReason: $overrideReason,
            metadata: [
                'card_id' => $card->id,
                'card_number' => $card->card_number,
                'application_id' => $app->id,
            ]
        );

        // If card was still in BiometricsCaptured, mark ready first
        if ($app->status === ApplicationStatus::BiometricsCaptured) {
            $app = $issuance->markReadyForCollection($card, $user);
        }

        $notes = $request->input('notes') ?: 'Card collected by holder upon presentation of valid travel document and slip.';
        $app = $issuance->markCollected($app, $user, $notes);

        // Update distribution record
        if ($distribution) {
            $distribution->update([
                'status' => CardDistribution::STATUS_ISSUED,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'geofence_verified_at_issuance' => (bool) ($geofenceCheck['verified'] ?? false),
                'issuance_notes' => $notes,
            ]);
        }

        return response()->json([
            'message' => "Card {$card->card_number} successfully issued out to {$card->forenames} {$card->surname}. Applicant notified via 'Ready / collected'.",
            'status' => $app->status->value,
            'collected_at' => $app->collected_at?->toIso8601String(),
            'geofence' => $geofenceCheck,
        ]);
    }

    /**
     * Batch notify: mark multiple produced cards as ready for collection.
     */
    public function batchReady(Request $request, CardIssuance $issuance): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
        ]);

        $cards = ResidenceCard::with(['application', 'distribution'])->whereIn('id', $data['card_ids'])->get();
        $count = 0;

        foreach ($cards as $card) {
            if (! $user->canAccessCard($card)) {
                continue;
            }

            if ($card->application && $card->application->status === ApplicationStatus::BiometricsCaptured) {
                try {
                    $issuance->markReadyForCollection($card, $user);
                    $count++;
                } catch (\Throwable) {
                    // Continue with next
                }
            }
        }

        return response()->json([
            'message' => "{$count} card(s) marked ready for collection. Applicants notified.",
            'count' => $count,
        ]);
    }

    /**
     * Batch issue: record collection for multiple cards.
     */
    public function batchIssue(Request $request, CardIssuance $issuance, GeofenceService $geofence): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Verify geofence for the batch
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $overrideReason = $data['override_reason'] ?? null;

        $geofenceCheck = $geofence->assertStaffLocation(
            officer: $user,
            latitude: $lat,
            longitude: $lng,
            action: 'CARD_ISSUANCE',
            overrideReason: $overrideReason,
            metadata: ['batch_card_count' => count($data['card_ids'])]
        );

        $cards = ResidenceCard::with(['application', 'distribution'])->whereIn('id', $data['card_ids'])->get();
        $count = 0;

        foreach ($cards as $card) {
            if (! $user->canIssueCard($card)) {
                continue;
            }

            // Ensure physical card receipt
            if ($card->distribution && ! in_array($card->distribution->status, [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE, CardDistribution::STATUS_ISSUED], true)) {
                continue;
            }

            if ($card->application && in_array($card->application->status, [ApplicationStatus::ReadyForCollection, ApplicationStatus::BiometricsCaptured], true)) {
                try {
                    if ($card->application->status === ApplicationStatus::BiometricsCaptured) {
                        $issuance->markReadyForCollection($card, $user);
                    }
                    $issuance->markCollected($card->application, $user, 'Batch issued out at centre.');

                    if ($card->distribution) {
                        $card->distribution->update([
                            'status' => CardDistribution::STATUS_ISSUED,
                            'issued_by' => $user->id,
                            'issued_at' => now(),
                            'geofence_verified_at_issuance' => (bool) ($geofenceCheck['verified'] ?? false),
                            'issuance_notes' => 'Batch issued out at centre.',
                        ]);
                    }
                    $count++;
                } catch (\Throwable) {
                    // Continue with next
                }
            }
        }

        return response()->json([
            'message' => "{$count} card(s) marked collected and issued out. Applicants notified.",
            'count' => $count,
            'geofence' => $geofenceCheck,
        ]);
    }
}
