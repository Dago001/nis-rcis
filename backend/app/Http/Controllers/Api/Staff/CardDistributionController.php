<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Models\CardDistribution;
use App\Models\NisCommand;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\GeofenceService;
use App\Support\Audit;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CardDistributionController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->input('status');
        $zoneId = $request->input('zone_id');
        $commandId = $request->input('command_id');
        $search = $request->input('search');
        $perPage = (int) ($request->input('per_page', 15));

        $query = CardDistribution::query()
            ->with([
                'card.applicant',
                'application.enrollmentCenter',
                'destinationZone',
                'destinationCommand',
                'destinationCenter',
                'dispatcher:id,fullname,service_number',
                'receiver:id,fullname,service_number',
                'issuer:id,fullname,service_number',
            ])
            ->forStaffJurisdiction($user)
            ->latest('id');

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($zoneId) && $user->isHeadquarters()) {
            $query->where('destination_zone_id', $zoneId);
        }

        if (! empty($commandId) && ($user->isHeadquarters() || ($user->isZonal() && (int) $user->zone_id === (int) $zoneId))) {
            $query->where('destination_command_id', $commandId);
        }

        if (! empty($search)) {
            $term = trim($search);
            $pattern = Like::contains($term);
            $query->where(function ($q) use ($pattern) {
                $q->where('card_number', 'like', $pattern)
                  ->orWhere('application_number', 'like', $pattern)
                  ->orWhere('dispatch_reference', 'like', $pattern)
                  ->orWhere('waybill_number', 'like', $pattern)
                  ->orWhereHas('card', fn ($cq) => $cq->where('surname', 'like', $pattern)->orWhere('forenames', 'like', $pattern));
            });
        }

        $paginated = $query->paginate($perPage);

        // Summary counts scoped to user's jurisdiction
        $base = CardDistribution::query()->forStaffJurisdiction($user);
        $counts = [
            'awaiting_dispatch' => (clone $base)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count(),
            'dispatched' => (clone $base)->where('status', CardDistribution::STATUS_DISPATCHED)->count(),
            'received' => (clone $base)->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->count(),
            'issued' => (clone $base)->where('status', CardDistribution::STATUS_ISSUED)->count(),
            'total' => (clone $base)->count(),
        ];

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distribution = CardDistribution::with([
            'card.applicant',
            'application.enrollmentCenter',
            'destinationZone',
            'destinationCommand',
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

        return response()->json(['data' => $distribution]);
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
            : 'DSP-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $distributions = CardDistribution::whereIn('id', $data['distribution_ids'])
            ->whereIn('status', [CardDistribution::STATUS_AWAITING_DISPATCH, CardDistribution::STATUS_DISPATCHED])
            ->get();

        if ($distributions->isEmpty()) {
            throw ValidationException::withMessages([
                'distribution' => 'No eligible cards awaiting dispatch were selected.',
            ]);
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
