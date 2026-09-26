<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Resources\CardResource;
use App\Models\CardPrintJob;
use App\Models\CardStockBatch;
use App\Models\ResidenceCard;
use App\Services\CardProduction;
use App\Services\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Card production: blank card stock, the print log, and batch printing.
 */
class CardStockController
{
    public function index(CardProduction $production): JsonResponse
    {
        return response()->json([
            'stock' => $production->stock(),
            'batches' => CardStockBatch::with('receiver:id,fullname')->withCount([
                'printJobs as printed_count' => fn ($q) => $q->where('outcome', CardPrintJob::PRINTED),
                'printJobs as spoiled_count' => fn ($q) => $q->where('outcome', CardPrintJob::SPOILED),
            ])->latest('received_on')->latest('id')->get()->map(fn (CardStockBatch $b) => [
                'id' => $b->id, 'batch_number' => $b->batch_number, 'quantity' => $b->quantity,
                'serial_from' => $b->serial_from, 'serial_to' => $b->serial_to, 'received_on' => $b->received_on?->toDateString(),
                'received_by' => $b->receiver?->fullname, 'notes' => $b->notes,
                'printed' => $b->printed_count, 'spoiled' => $b->spoiled_count, 'remaining' => $b->quantity - $b->printed_count - $b->spoiled_count,
            ]),
            'jobs' => CardPrintJob::with(['card:id,card_number,surname,forenames', 'printer:id,fullname', 'batch:id,batch_number'])
                ->latest('id')->limit(100)->get()->map(fn (CardPrintJob $j) => [
                    'id' => $j->id, 'outcome' => $j->outcome, 'spoil_reason' => $j->spoil_reason,
                    'card' => $j->card ? ['id' => $j->card->id, 'card_number' => $j->card->card_number, 'holder' => "{$j->card->surname}, {$j->card->forenames}"] : null,
                    'batch' => $j->batch?->batch_number, 'printed_by' => $j->printer?->fullname, 'at' => $j->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function receive(Request $request, CardProduction $production): JsonResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:50', 'unique:card_stock_batches,batch_number'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'serial_from' => ['nullable', 'string', 'max:30'],
            'serial_to' => ['nullable', 'string', 'max:30'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $production->receive($request->user(), $data);

        return response()->json(['stock' => $production->stock()], 201);
    }

    public function record(Request $request, int $id, CardProduction $production): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in([CardPrintJob::PRINTED, CardPrintJob::SPOILED])],
            'reason' => ['required_if:outcome,SPOILED', 'nullable', 'string', 'max:255'],
        ]);
        $production->record(ResidenceCard::findOrFail($id), $request->user(), $data['outcome'], $data['reason'] ?? null);

        return response()->json(['stock' => $production->stock()], 201);
    }

    /** Several cards for one print run (active cards only). */
    public function batch(Request $request, DocumentStorage $storage, CardProduction $production): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'string', 'regex:/^\d+(,\d+){0,49}$/']]);
        $cards = ResidenceCard::with('renewals')->whereIn('id', explode(',', $data['ids']))->get()
            ->filter(fn (ResidenceCard $c) => $c->status->isActive())->values();

        return response()->json([
            'stock' => $production->stock(),
            'data' => $cards->map(fn (ResidenceCard $card) => [
                'data' => new CardResource($card),
                'photo_url' => $storage->temporaryUrlForPath($card->photo_path),
                'signature_url' => $storage->temporaryUrlForPath($card->signature_path),
                'verification_url' => config('nis.frontend_url').'/verify?token='.$card->verification_token,
            ]),
        ]);
    }
}
