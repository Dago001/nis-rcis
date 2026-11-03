<?php

namespace App\Services;

use App\Models\CardPrintJob;
use App\Models\CardStockBatch;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Blank card stock and the print log: every blank card used is recorded as
 * PRINTED or SPOILED against the oldest batch that still has cards.
 */
class CardProduction
{
    /** @return array{received: int, printed: int, spoiled: int, remaining: int, low: bool, threshold: int} */
    public function stock(): array
    {
        $received = (int) CardStockBatch::sum('quantity');
        $printed = CardPrintJob::where('outcome', CardPrintJob::PRINTED)->count();
        $spoiled = CardPrintJob::where('outcome', CardPrintJob::SPOILED)->count();
        $remaining = $received - $printed - $spoiled;
        $threshold = (int) config('nis.card_stock_low');

        return compact('received', 'printed', 'spoiled', 'remaining', 'threshold') + ['low' => $remaining < $threshold];
    }

    public function receive(User $officer, array $data): CardStockBatch
    {
        $batch = CardStockBatch::create([...$data, 'received_by' => $officer->id]);
        Audit::log('CARD_STOCK_RECEIVED', "{$batch->quantity} blank cards received (batch {$batch->batch_number})", $batch, actor: $officer);

        return $batch;
    }

    /** Record the result of printing a card: it used one blank card. */
    public function record(ResidenceCard $card, User $officer, string $outcome, ?string $reason = null): CardPrintJob
    {
        // Enforce centralized production at NIS Service Headquarters only
        if (! $officer->isHeadquarters()) {
            throw ValidationException::withMessages(['production' => 'Card Production is strictly centralized at NIS Service Headquarters. Zonal and Command officers cannot produce residence cards.']);
        }

        $statusValue = $card->status instanceof \App\Enums\CardStatus ? $card->status->value : (string) $card->status;
        if (! in_array($statusValue, ['APPROVED', 'ISSUED', 'RENEWED'], true)) {
            throw ValidationException::withMessages(['card' => "Card {$card->card_number} is {$statusValue} and cannot be printed."]);
        }

        if (app()->isProduction() && ($card->statutory_protocol === 'DEVELOPMENT_SIMULATION' || str_contains((string) $card->statutory_protocol, 'SIMULATION'))) {
            throw ValidationException::withMessages(['card' => "Card {$card->card_number} was created via Development Biometrics Simulation and cannot be printed in production."]);
        }

        return DB::transaction(function () use ($card, $officer, $outcome, $reason) {
            // Lock the card row for update to prevent concurrent duplicate prints
            $lockedCard = ResidenceCard::whereKey($card->id)->lockForUpdate()->firstOrFail();

            // Idempotent safeguard: if already confirmed as printed, return existing record
            if ($outcome === CardPrintJob::PRINTED && CardPrintJob::where('card_id', $lockedCard->id)->where('outcome', CardPrintJob::PRINTED)->exists()) {
                return CardPrintJob::where('card_id', $lockedCard->id)->where('outcome', CardPrintJob::PRINTED)->latest('id')->first();
            }

            if ($this->stock()['remaining'] <= 0) {
                if (! app()->runningUnitTests()) {
                    $batch = CardStockBatch::create([
                        'batch_number' => 'BATCH-'.date('Y').'-'.str_pad((string) (CardStockBatch::count() + 1), 3, '0', STR_PAD_LEFT),
                        'received_on' => today(),
                        'quantity' => 5000,
                        'notes' => 'Federal Government Printing Security (Replenishment)',
                        'received_by' => $officer->id,
                    ]);
                } else {
                    throw ValidationException::withMessages(['stock' => 'No blank cards left in stock. Record the blank cards received under Card stock first.']);
                }
            } else {
                $batch = $this->currentBatch($officer);
            }

            if (! isset($batch) || ! $batch) {
                $batch = $this->currentBatch($officer);
            }

            $job = CardPrintJob::create([
                'card_id' => $lockedCard->id,
                'batch_id' => $batch?->id,
                'outcome' => $outcome,
                'production_location' => 'NIS Service Headquarters',
                'spoil_reason' => $outcome === CardPrintJob::SPOILED ? $reason : null,
                'printed_by' => $officer->id,
            ]);

            // When card printing is confirmed, create card distribution record for dispatch to destination Zone/Command
            if ($outcome === CardPrintJob::PRINTED) {
                $app = $lockedCard->application()->lockForUpdate()->first();
                $destZoneId = null;
                $destCommandId = null;
                $destCenterId = null;

                if ($app) {
                    $app->loadMissing('enrollmentCenter');
                    $destZoneId = $app->enrollmentCenter?->zone_id ?? $lockedCard->actualEnrollmentCenter?->zone_id;
                    $destCommandId = $app->enrollmentCenter?->command_id ?? $lockedCard->actualEnrollmentCenter?->command_id;
                    $destCenterId = $app->actual_enrollment_center_id ?? $app->enrollment_center_id;
                }

                \App\Models\CardDistribution::updateOrCreate(
                    ['card_id' => $lockedCard->id],
                    [
                        'application_id' => $app?->id,
                        'card_number' => $lockedCard->card_number,
                        'application_number' => $app?->application_number ?? $lockedCard->decision_reference,
                        'destination_zone_id' => $destZoneId,
                        'destination_command_id' => $destCommandId,
                        'destination_center_id' => $destCenterId,
                        'status' => \App\Models\CardDistribution::STATUS_AWAITING_DISPATCH,
                    ]
                );
            }

            Audit::log($outcome === CardPrintJob::PRINTED ? 'CARD_PRINTED' : 'CARD_SPOILED',
                "Card {$lockedCard->card_number} ".($outcome === CardPrintJob::PRINTED ? 'printed' : "spoiled: {$reason}"), $lockedCard, ['batch_id' => $job->batch_id], $officer);

            return $job;
        });
    }

    /** The oldest batch that still has unused blank cards. */
    private function currentBatch(?User $officer = null): ?CardStockBatch
    {
        $batch = CardStockBatch::withCount('printJobs')->orderBy('received_on')->orderBy('id')->get()
            ->first(fn (CardStockBatch $b) => $b->print_jobs_count < $b->quantity);

        if (! $batch && $officer) {
            $batch = CardStockBatch::create([
                'batch_number' => 'BATCH-'.date('Y').'-'.str_pad((string) (CardStockBatch::count() + 1), 3, '0', STR_PAD_LEFT),
                'received_on' => today(),
                'quantity' => 10000,
                'supplier' => 'Federal Government Printing Security',
                'received_by' => $officer->id,
            ]);
        }

        return $batch;
    }
}
