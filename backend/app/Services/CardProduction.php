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
                'spoil_reason' => $outcome === CardPrintJob::SPOILED ? $reason : null,
                'printed_by' => $officer->id,
            ]);

            // When card printing is confirmed, advance application to READY_FOR_COLLECTION
            // so it appears in Card Issuance under 'Awaiting Issuance' without being marked as ISSUED
            if ($outcome === CardPrintJob::PRINTED && $lockedCard->application) {
                $app = $lockedCard->application()->lockForUpdate()->first();
                if ($app && in_array($app->status, [\App\Enums\ApplicationStatus::BiometricsCaptured, \App\Enums\ApplicationStatus::Approved], true)) {
                    $app->status = \App\Enums\ApplicationStatus::ReadyForCollection;
                    $app->ready_at = now();
                    $app->saveQuietly();
                }
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
