<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Models\CardDistribution;
use App\Models\CardPrintJob;
use App\Models\NisCommand;
use App\Models\NisZone;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\GeofenceService;
use App\Support\Audit;
use App\Support\Like;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardDistributionController
{
    /**
     * Display card distribution dashboard metrics, command breakdown, and detailed card register.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->input('status');
        $zoneId = $request->filled('zone_id') ? (int) $request->input('zone_id') : null;
        $commandId = $request->filled('command_id') ? (int) $request->input('command_id') : null;
        $type = $request->input('type'); // NEW | RENEWAL
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $perPage = max(10, min(100, (int) ($request->input('per_page', 15))));

        // Scope restrictions based on role
        if (! $user->isHeadquarters()) {
            if ($user->isCommand() && $user->command_id) {
                $commandId = (int) $user->command_id;
                $zoneId = $user->zone_id ? (int) $user->zone_id : null;
            } elseif ($user->isZonal() && $user->zone_id) {
                $zoneId = (int) $user->zone_id;
                if ($commandId) {
                    $validCmd = NisCommand::where('id', $commandId)->where('zone_id', $zoneId)->exists();
                    if (! $validCmd) {
                        $commandId = null;
                    }
                }
            }
        }

        // 1. Detailed Register Query
        $query = CardDistribution::query()
            ->with([
                'card.printJobs' => fn ($pq) => $pq->where('outcome', CardPrintJob::PRINTED)->latest('printed_at'),
                'application',
                'destinationZone',
                'destinationCommand.zone',
                'destinationCenter',
                'dispatcher:id,fullname,service_number',
                'receiver:id,fullname,service_number',
                'issuer:id,fullname,service_number',
            ])
            ->forStaffJurisdiction($user)
            ->latest('id');

        // Apply filters
        if (! empty($status) && $status !== 'ALL') {
            if ($status === 'RECEIVED') {
                $query->whereIn('status', [
                    CardDistribution::STATUS_RECEIVED,
                    CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($zoneId)) {
            $query->where('destination_zone_id', $zoneId);
        }

        if (! empty($commandId)) {
            $query->where('destination_command_id', $commandId);
        }

        if (! empty($type)) {
            $query->whereHas('application', fn ($aq) => $aq->where('type', strtoupper($type)));
        }

        if (! empty($dateFrom)) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if (! empty($dateTo)) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if (! empty($search)) {
            $term = trim($search);
            $pattern = Like::contains($term);
            $query->where(function ($q) use ($pattern) {
                $q->where('card_number', 'like', $pattern)
                  ->orWhere('application_number', 'like', $pattern)
                  ->orWhere('dispatch_reference', 'like', $pattern)
                  ->orWhere('waybill_number', 'like', $pattern)
                  ->orWhereHas('card', fn ($cq) => $cq->where('surname', 'like', $pattern)->orWhere('forenames', 'like', $pattern)->orWhere('passport_number', 'like', $pattern));
            });
        }

        $paginated = $query->paginate($perPage);

        // Format items for frontend
        $formattedData = collect($paginated->items())->map(function (CardDistribution $d) {
            $card = $d->card;
            $app = $d->application;
            $printedJob = $card?->printJobs?->first();
            $productionDate = $printedJob?->printed_at ?? $card?->created_at ?? $d->created_at;

            return [
                'id' => $d->id,
                'card_id' => $d->card_id,
                'application_id' => $d->application_id,
                'card_number' => $d->card_number,
                'application_number' => $d->application_number,
                'applicant_name' => $card ? trim("{$card->surname} {$card->forenames}") : ($app ? trim("{$app->surname} {$app->forenames}") : '—'),
                'applicant_passport' => $card?->passport_number ?? $app?->passport_number ?? '—',
                'application_type' => $app?->type ?? 'NEW',
                'destination_zone_id' => $d->destination_zone_id,
                'destination_zone' => $d->destinationZone?->name ?? 'Headquarters',
                'destination_command_id' => $d->destination_command_id,
                'destination_command' => $d->destinationCommand?->name ?? 'Command',
                'destination_center' => $d->destinationCenter?->name ?? 'Enrollment Center',
                'production_date' => $productionDate ? Carbon::parse($productionDate)->toIso8601String() : null,
                'dispatch_reference' => $d->dispatch_reference,
                'dispatch_date' => $d->dispatched_at ? Carbon::parse($d->dispatched_at)->toIso8601String() : null,
                'dispatched_by_name' => $d->dispatcher?->fullname,
                'received_date' => $d->received_at ? Carbon::parse($d->received_at)->toIso8601String() : null,
                'received_by_name' => $d->receiver?->fullname,
                'issued_date' => $d->issued_at ? Carbon::parse($d->issued_at)->toIso8601String() : null,
                'issuer_by_name' => $d->issuer?->fullname,
                'status' => $d->status,
                'courier_service' => $d->courier_service,
                'waybill_number' => $d->waybill_number,
                'dispatch_notes' => $d->dispatch_notes,
                'receipt_notes' => $d->receipt_notes,
                'geofence_verified_receipt' => (bool) $d->geofence_verified_receipt,
            ];
        });

        // 2. Summary Statistics for Selected Scope
        $statsBase = CardDistribution::query()->forStaffJurisdiction($user);
        if (! empty($zoneId)) {
            $statsBase->where('destination_zone_id', $zoneId);
        }
        if (! empty($commandId)) {
            $statsBase->where('destination_command_id', $commandId);
        }
        if (! empty($type)) {
            $statsBase->whereHas('application', fn ($aq) => $aq->where('type', strtoupper($type)));
        }

        $awaitingDispatch = (clone $statsBase)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count();
        $inTransit = (clone $statsBase)->where('status', CardDistribution::STATUS_DISPATCHED)->count();
        $received = (clone $statsBase)->whereIn('status', [
            CardDistribution::STATUS_RECEIVED,
            CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
            CardDistribution::STATUS_ISSUED,
        ])->count();
        $issued = (clone $statsBase)->where('status', CardDistribution::STATUS_ISSUED)->count();

        // Total cards produced within selected scope
        $cardsProducedQuery = ResidenceCard::query()->forStaffJurisdiction($user);
        if (! empty($commandId)) {
            $cardsProducedQuery->where(function ($q) use ($commandId) {
                $q->whereHas('distribution', fn ($dq) => $dq->where('destination_command_id', $commandId))
                  ->orWhereHas('application', fn ($aq) => $aq->where('command_id', $commandId));
            });
        } elseif (! empty($zoneId)) {
            $cardsProducedQuery->where(function ($q) use ($zoneId) {
                $q->whereHas('distribution', fn ($dq) => $dq->where('destination_zone_id', $zoneId))
                  ->orWhereHas('application', fn ($aq) => $aq->where('zone_id', $zoneId));
            });
        }
        if (! empty($type)) {
            $cardsProducedQuery->whereHas('application', fn ($aq) => $aq->where('type', strtoupper($type)));
        }

        $totalProduced = $cardsProducedQuery->where(function ($q) {
            $q->whereHas('printJobs', fn ($pj) => $pj->where('outcome', CardPrintJob::PRINTED))
              ->orWhereIn('status', [CardStatus::Issued, CardStatus::Renewed])
              ->orWhereHas('distribution');
        })->count();

        // Reconcile total produced: Total produced is cumulative; cannot be less than active distribution population
        $totalProduced = max($totalProduced, $awaitingDispatch + $inTransit + $received);

        $summary = [
            'total_cards_produced' => $totalProduced,
            'awaiting_dispatch' => $awaitingDispatch,
            'in_transit' => $inTransit,
            'received' => $received,
            'issued' => $issued,
        ];

        // 3. Command Distribution Summary Table
        $cmdQuery = NisCommand::query()->with('zone:id,name,code')->where('is_active', true);
        if ($user->isCommand() && $user->command_id) {
            $cmdQuery->where('id', $user->command_id);
        } elseif ($user->isZonal() && $user->zone_id) {
            $cmdQuery->where('zone_id', $user->zone_id);
        } elseif (! empty($zoneId)) {
            $cmdQuery->where('zone_id', $zoneId);
        }
        if (! empty($commandId)) {
            $cmdQuery->where('id', $commandId);
        }

        $commandsList = $cmdQuery->orderBy('name')->get();

        // Fast aggregate distribution counts by command
        $distAggregates = CardDistribution::query()
            ->selectRaw("
                destination_command_id,
                status,
                count(*) as total
            ")
            ->whereNotNull('destination_command_id')
            ->groupBy('destination_command_id', 'status')
            ->get();

        $commandsSummary = $commandsList->map(function ($cmd, $index) use ($distAggregates) {
            $cmdRecords = $distAggregates->where('destination_command_id', $cmd->id);

            $awaiting = (int) $cmdRecords->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->sum('total');
            $transit = (int) $cmdRecords->where('status', CardDistribution::STATUS_DISPATCHED)->sum('total');
            $rec = (int) $cmdRecords->whereIn('status', [
                CardDistribution::STATUS_RECEIVED,
                CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                CardDistribution::STATUS_ISSUED,
            ])->sum('total');
            $iss = (int) $cmdRecords->where('status', CardDistribution::STATUS_ISSUED)->sum('total');
            $produced = $awaiting + $transit + $rec;

            return [
                'sn' => $index + 1,
                'id' => $cmd->id,
                'name' => $cmd->name,
                'code' => $cmd->code,
                'state' => $cmd->state,
                'zone_id' => $cmd->zone_id,
                'zone_name' => $cmd->zone?->name ?? 'Zone',
                'zone_code' => $cmd->zone?->code ?? '',
                'total_produced' => $produced,
                'awaiting_dispatch' => $awaiting,
                'in_transit' => $transit,
                'received' => $rec,
                'issued' => $iss,
            ];
        })->values();

        // 4. Reference Lists for Filtering
        $zonesQuery = NisZone::where('is_active', true)->orderBy('code');
        if ($user->isZonal() && $user->zone_id) {
            $zonesQuery->where('id', $user->zone_id);
        } elseif ($user->isCommand() && $user->zone_id) {
            $zonesQuery->where('id', $user->zone_id);
        }
        $zones = $zonesQuery->get(['id', 'code', 'name']);

        $allCommandsQuery = NisCommand::where('is_active', true)->orderBy('name');
        if ($user->isCommand() && $user->command_id) {
            $allCommandsQuery->where('id', $user->command_id);
        } elseif ($user->isZonal() && $user->zone_id) {
            $allCommandsQuery->where('zone_id', $user->zone_id);
        }
        $commands = $allCommandsQuery->get(['id', 'code', 'name', 'zone_id', 'state']);

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
            'summary' => $summary,
            'commands_summary' => $commandsSummary,
            'zones' => $zones,
            'commands' => $commands,
            'active_filters' => [
                'status' => $status ?: 'ALL',
                'zone_id' => $zoneId,
                'command_id' => $commandId,
                'type' => $type ?: 'ALL',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show single card distribution dossier with complete lifecycle tracking.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distribution = CardDistribution::with([
            'card.printJobs' => fn ($pq) => $pq->where('outcome', CardPrintJob::PRINTED)->latest('printed_at'),
            'application.enrollmentCenter',
            'destinationZone',
            'destinationCommand.zone',
            'destinationCenter',
            'dispatcher:id,fullname,service_number',
            'receiver:id,fullname,service_number',
            'issuer:id,fullname,service_number',
        ])->findOrFail($id);

        if (! $user->isHeadquarters()) {
            if ($user->isZonal() && (int) $distribution->destination_zone_id !== (int) $user->zone_id) {
                abort(403, 'Access denied: Card distribution is outside your assigned Zone.');
            }
            if ($user->isCommand() && (int) $distribution->destination_command_id !== (int) $user->command_id) {
                abort(403, 'Access denied: Card distribution is outside your assigned Command.');
            }
        }

        $card = $distribution->card;
        $app = $distribution->application;
        $printedJob = $card?->printJobs?->first();

        return response()->json([
            'data' => [
                'id' => $distribution->id,
                'card_id' => $distribution->card_id,
                'application_id' => $distribution->application_id,
                'card_number' => $distribution->card_number,
                'application_number' => $distribution->application_number,
                'applicant_name' => $card ? trim("{$card->surname} {$card->forenames}") : ($app ? trim("{$app->surname} {$app->forenames}") : '—'),
                'applicant_passport' => $card?->passport_number ?? $app?->passport_number ?? '—',
                'application_type' => $app?->type ?? 'NEW',
                'destination_zone' => $distribution->destinationZone?->name ?? 'Headquarters',
                'destination_command' => $distribution->destinationCommand?->name ?? 'Command',
                'destination_center' => $distribution->destinationCenter?->name ?? 'Enrollment Center',
                'status' => $distribution->status,
                'dispatch_reference' => $distribution->dispatch_reference,
                'courier_service' => $distribution->courier_service,
                'waybill_number' => $distribution->waybill_number,
                'dispatch_notes' => $distribution->dispatch_notes,
                'dispatched_at' => $distribution->dispatched_at ? Carbon::parse($distribution->dispatched_at)->toIso8601String() : null,
                'dispatched_by' => $distribution->dispatcher?->fullname,
                'receipt_notes' => $distribution->receipt_notes,
                'received_at' => $distribution->received_at ? Carbon::parse($distribution->received_at)->toIso8601String() : null,
                'received_by' => $distribution->receiver?->fullname,
                'issued_at' => $distribution->issued_at ? Carbon::parse($distribution->issued_at)->toIso8601String() : null,
                'issued_by' => $distribution->issuer?->fullname,
                'production_date' => $printedJob?->printed_at ?? $card?->created_at,
                'geofence_verified_receipt' => (bool) $distribution->geofence_verified_receipt,
                'receipt_latitude' => $distribution->receipt_latitude,
                'receipt_longitude' => $distribution->receipt_longitude,
            ],
        ]);
    }

    /**
     * Dispatch cards from Headquarters to destination Zone/Command.
     */
    public function dispatchBatch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isHeadquarters()) {
            abort(403, 'Card dispatch operations can only be initiated by authorized Headquarters officers.');
        }

        $data = $request->validate([
            'distribution_ids' => ['required', 'array', 'min:1'],
            'distribution_ids.*' => ['integer'],
            'dispatch_reference' => ['nullable', 'string', 'max:60'],
            'courier_service' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'dispatch_notes' => ['nullable', 'string', 'max:500'],
            'destination_command_id' => ['nullable', 'integer', 'exists:nis_commands,id'],
        ]);

        $dispatchRef = ! empty($data['dispatch_reference'])
            ? trim($data['dispatch_reference'])
            : 'HQ-DSP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $distributions = CardDistribution::whereIn('id', $data['distribution_ids'])
            ->whereIn('status', [CardDistribution::STATUS_AWAITING_DISPATCH, CardDistribution::STATUS_DISPATCHED])
            ->get();

        if ($distributions->isEmpty()) {
            throw ValidationException::withMessages([
                'distribution' => 'No eligible cards awaiting dispatch were selected.',
            ]);
        }

        // Validate that cards have been physically printed before dispatch
        foreach ($distributions as $dist) {
            $card = $dist->card;
            if (! $card || ! $card->printJobs()->where('outcome', CardPrintJob::PRINTED)->exists()) {
                throw ValidationException::withMessages([
                    'distribution' => "Card {$dist->card_number} has not been physically printed. Card distribution requires confirmed card production.",
                ]);
            }
        }

        $count = 0;
        DB::transaction(function () use ($distributions, $user, $data, $dispatchRef, &$count) {
            foreach ($distributions as $dist) {
                $destCommandId = $data['destination_command_id'] ?? $dist->destination_command_id;
                $destZoneId = $dist->destination_zone_id;

                if ($data['destination_command_id'] ?? null) {
                    $cmd = NisCommand::find($data['destination_command_id']);
                    if ($cmd) {
                        $destZoneId = $cmd->zone_id;
                    }
                }

                $dist->fill([
                    'status' => CardDistribution::STATUS_DISPATCHED,
                    'dispatch_reference' => $dispatchRef,
                    'courier_service' => $data['courier_service'] ?? $dist->courier_service,
                    'waybill_number' => $data['waybill_number'] ?? $dist->waybill_number,
                    'dispatch_notes' => $data['dispatch_notes'] ?? $dist->dispatch_notes,
                    'destination_command_id' => $destCommandId,
                    'destination_zone_id' => $destZoneId,
                    'dispatched_by' => $user->id,
                    'dispatched_at' => now(),
                ])->save();

                Audit::log(
                    'CARD_DISPATCHED',
                    "Card {$dist->card_number} dispatched to Command #{$destCommandId} under reference {$dispatchRef}",
                    $dist,
                    context: ['dispatch_reference' => $dispatchRef, 'waybill' => $data['waybill_number'] ?? null],
                    actor: $user
                );

                $count++;
            }
        });

        return response()->json([
            'message' => "Successfully dispatched {$count} residence card(s) under dispatch reference {$dispatchRef}.",
            'count' => $count,
            'dispatch_reference' => $dispatchRef,
        ]);
    }

    /**
     * Confirm physical receipt of dispatched cards at destination Command.
     */
    public function confirmReceiptBatch(Request $request, GeofenceService $geofence): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'distribution_ids' => ['required', 'array', 'min:1'],
            'distribution_ids.*' => ['integer'],
            'receipt_notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $distributions = CardDistribution::with(['card', 'application'])
            ->whereIn('id', $data['distribution_ids'])
            ->get();

        if ($distributions->isEmpty()) {
            throw ValidationException::withMessages([
                'distribution' => 'No card distribution records found for receipt confirmation.',
            ]);
        }

        // Jurisdiction and status check
        foreach ($distributions as $dist) {
            if (! in_array($dist->status, [CardDistribution::STATUS_DISPATCHED, CardDistribution::STATUS_AWAITING_DISPATCH], true)) {
                throw ValidationException::withMessages([
                    'distribution' => "Card {$dist->card_number} has already been marked as received.",
                ]);
            }

            if (! $user->isHeadquarters()) {
                if ($user->isCommand() && (int) $dist->destination_command_id !== (int) $user->command_id) {
                    abort(403, "Access denied: Card {$dist->card_number} is destined for another Command.");
                }
                if ($user->isZonal() && (int) $dist->destination_zone_id !== (int) $user->zone_id) {
                    abort(403, "Access denied: Card {$dist->card_number} is destined for another Zone.");
                }
            }
        }

        // Physical Geofencing verification
        $targetCommandId = $user->command_id ?? $distributions->first()->destination_command_id;
        $geofenceResult = $geofence->verifyStaffLocation(
            $user,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            'CARD_RECEIPT',
            $targetCommandId,
            null,
            $data['override_reason'] ?? null
        );

        if (! $geofenceResult['verified']) {
            throw ValidationException::withMessages([
                'geofence' => $geofenceResult['message'],
            ]);
        }

        $count = 0;
        DB::transaction(function () use ($distributions, $user, $data, &$count) {
            foreach ($distributions as $dist) {
                $dist->fill([
                    'status' => CardDistribution::STATUS_RECEIVED,
                    'receipt_notes' => $data['receipt_notes'] ?? 'Physical package received and verified at Command.',
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'geofence_verified_receipt' => true,
                    'receipt_latitude' => $data['latitude'] ?? null,
                    'receipt_longitude' => $data['longitude'] ?? null,
                ])->save();

                // Once physically received at command, application advances to READY_FOR_COLLECTION
                if ($dist->application && $dist->application->status !== ApplicationStatus::Issued) {
                    $dist->application->update([
                        'status' => ApplicationStatus::ReadyForCollection,
                        'ready_at' => now(),
                    ]);
                }

                Audit::log(
                    'CARD_RECEIPT_CONFIRMED',
                    "Card {$dist->card_number} physical receipt confirmed at destination Command",
                    $dist,
                    context: ['receipt_notes' => $data['receipt_notes'] ?? null],
                    actor: $user
                );

                $count++;
            }
        });

        return response()->json([
            'message' => "Successfully confirmed physical receipt of {$count} residence card(s). Cards are now available for local issuance.",
            'count' => $count,
        ]);
    }
}
