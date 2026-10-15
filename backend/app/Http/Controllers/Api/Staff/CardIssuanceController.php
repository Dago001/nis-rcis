<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ResidenceCard;
use App\Services\CardIssuance;
use App\Services\DocumentStorage;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller for Card Issuance & Collection Desk.
 * Manages the handover of produced cards to applicants and notification delivery.
 */
class CardIssuanceController
{
    public function index(Request $request, DocumentStorage $storage): JsonResponse
    {
        $stage = $request->input('stage', 'awaiting_collection');
        $search = $request->input('search');
        $centerId = $request->input('center_id');

        $query = ResidenceCard::query()
            ->with([
                'application.enrollmentCenter',
                'application.statusHistory' => fn ($q) => $q->latest('id')->limit(5),
                'issuingOfficer:id,fullname,service_number',
            ])
            ->withCount(['printJobs as printed_count' => fn ($q) => $q->where('outcome', 'PRINTED')])
            ->latest('id');

        // Filter by stage
        match ($stage) {
            'ready_to_notify' => $query->whereHas('application', fn ($q) => $q->where('status', ApplicationStatus::BiometricsCaptured)),
            'awaiting_collection' => $query->whereHas('application', fn ($q) => $q->where('status', ApplicationStatus::ReadyForCollection)),
            'collected' => $query->whereHas('application', fn ($q) => $q->where('status', ApplicationStatus::Issued)),
            default => $query->whereHas('application', fn ($q) => $q->whereIn('status', [
                ApplicationStatus::BiometricsCaptured,
                ApplicationStatus::ReadyForCollection,
                ApplicationStatus::Issued,
            ])),
        };

        // Filter by enrollment center
        if (! empty($centerId)) {
            $query->whereHas('application', fn ($q) => $q->where('enrollment_center_id', $centerId));
        }

        // Search
        if (! empty($search)) {
            $term = strtoupper(trim($search));
            $query->where(function ($q) use ($term) {
                $q->where('card_number', $term)
                    ->orWhere('booklet_number', $term)
                    ->orWhere('passport_number', $term)
                    ->orWhere('surname', 'ilike', Like::contains($term))
                    ->orWhere('forenames', 'ilike', Like::contains($term))
                    ->orWhereHas('application', fn ($sq) => $sq->where('application_number', $term)
                        ->orWhere('reference_number', $term));
            });
        }

        $paginated = $query->paginate(25);

        // Compute summary counts
        $stats = [
            'awaiting_collection' => Application::where('status', ApplicationStatus::ReadyForCollection)->count(),
            'ready_to_notify' => Application::where('status', ApplicationStatus::BiometricsCaptured)->whereNotNull('card_id')->count(),
            'collected_total' => Application::where('status', ApplicationStatus::Issued)->count(),
            'total_produced' => Application::whereIn('status', [
                ApplicationStatus::BiometricsCaptured,
                ApplicationStatus::ReadyForCollection,
                ApplicationStatus::Issued,
            ])->whereNotNull('card_id')->count(),
        ];

        $items = collect($paginated->items())->map(function (ResidenceCard $card) use ($storage) {
            $app = $card->application;

            return [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'booklet_number' => $card->booklet_number,
                'status' => $card->status->value,
                'verification_status' => $card->verificationStatus(),
                'surname' => $card->surname,
                'forenames' => $card->forenames,
                'holder' => "{$card->surname}, {$card->forenames}",
                'passport_number' => $card->passport_number,
                'nationality' => $card->nationality,
                'issued_on' => $card->issued_on?->toDateString(),
                'expires_on' => $card->expires_on?->toDateString(),
                'printed_count' => (int) ($card->printed_count ?? 0),
                'photo_url' => $card->photo_path ? $storage->temporaryUrlForPath($card->photo_path) : null,
                'application' => $app ? [
                    'id' => $app->id,
                    'application_number' => $app->application_number,
                    'reference_number' => $app->reference_number,
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
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
        $card = ResidenceCard::with('application.enrollmentCenter')->findOrFail($id);
        $app = $card->application ?? throw ValidationException::withMessages(['card' => 'Card is not linked to an application.']);

        if ($app->status === ApplicationStatus::ReadyForCollection) {
            return response()->json([
                'message' => "Application {$app->application_number} is already marked ready for collection.",
                'status' => $app->status->value,
            ]);
        }

        $app = $issuance->markReadyForCollection($card, $request->user());

        return response()->json([
            'message' => "Card {$card->card_number} marked ready for collection. Applicant notified via 'Ready / collected'.",
            'status' => $app->status->value,
            'ready_at' => $app->ready_at?->toIso8601String(),
        ]);
    }

    /**
     * Issue out / hand over a produced card to the applicant upon collection.
     */
    public function issueOut(Request $request, int $id, CardIssuance $issuance): JsonResponse
    {
        $card = ResidenceCard::with('application.enrollmentCenter')->findOrFail($id);
        $app = $card->application ?? throw ValidationException::withMessages(['card' => 'Card is not linked to an application.']);

        if ($app->status === ApplicationStatus::Issued) {
            return response()->json([
                'message' => "Card {$card->card_number} was already collected on {$app->collected_at?->format('d/m/Y H:i')}.",
                'status' => $app->status->value,
            ]);
        }

        // If card was still in BiometricsCaptured, mark ready first
        if ($app->status === ApplicationStatus::BiometricsCaptured) {
            $app = $issuance->markReadyForCollection($card, $request->user());
        }

        $notes = $request->input('notes') ?: 'Card collected by holder upon presentation of valid travel document and slip.';
        $app = $issuance->markCollected($app, $request->user(), $notes);

        return response()->json([
            'message' => "Card {$card->card_number} successfully issued out to {$card->forenames} {$card->surname}. Applicant notified via 'Ready / collected'.",
            'status' => $app->status->value,
            'collected_at' => $app->collected_at?->toIso8601String(),
        ]);
    }

    /**
     * Batch notify: mark multiple produced cards as ready for collection.
     */
    public function batchReady(Request $request, CardIssuance $issuance): JsonResponse
    {
        $data = $request->validate([
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
        ]);

        $cards = ResidenceCard::with('application')->whereIn('id', $data['card_ids'])->get();
        $count = 0;

        foreach ($cards as $card) {
            if ($card->application && $card->application->status === ApplicationStatus::BiometricsCaptured) {
                try {
                    $issuance->markReadyForCollection($card, $request->user());
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
    public function batchIssue(Request $request, CardIssuance $issuance): JsonResponse
    {
        $data = $request->validate([
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
        ]);

        $cards = ResidenceCard::with('application')->whereIn('id', $data['card_ids'])->get();
        $count = 0;

        foreach ($cards as $card) {
            if ($card->application && in_array($card->application->status, [ApplicationStatus::ReadyForCollection, ApplicationStatus::BiometricsCaptured], true)) {
                try {
                    if ($card->application->status === ApplicationStatus::BiometricsCaptured) {
                        $issuance->markReadyForCollection($card, $request->user());
                    }
                    $issuance->markCollected($card->application, $request->user(), 'Batch issued out at centre.');
                    $count++;
                } catch (\Throwable) {
                    // Continue with next
                }
            }
        }

        return response()->json([
            'message' => "{$count} card(s) marked collected and issued out. Applicants notified.",
            'count' => $count,
        ]);
    }
}
