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
        if (! $card->status->isActive()) {
            throw ValidationException::withMessages(['card' => "Card {$card->card_number} is {$card->status->value} and cannot be printed."]);
        }

        return DB::transaction(function () use ($card, $officer, $outcome, $reason) {
            // Serialise stock movements so two desks cannot use the last blank card twice.
            DB::statement('LOCK TABLE card_print_jobs IN SHARE ROW EXCLUSIVE MODE');
            if ($this->stock()['remaining'] <= 0) {
                throw ValidationException::withMessages(['stock' => 'No blank cards left in stock. Record the blank cards received under Card stock first.']);
            }

            $job = CardPrintJob::create([
                'card_id' => $card->id,
                'batch_id' => $this->currentBatch()?->id,
                'outcome' => $outcome,
                'spoil_reason' => $outcome === CardPrintJob::SPOILED ? $reason : null,
                'printed_by' => $officer->id,
            ]);
            Audit::log($outcome === CardPrintJob::PRINTED ? 'CARD_PRINTED' : 'CARD_SPOILED',
                "Card {$card->card_number} ".($outcome === CardPrintJob::PRINTED ? 'printed' : "spoiled: {$reason}"), $card, ['batch_id' => $job->batch_id], $officer);

            return $job;
        });
    }

    /** The oldest batch that still has unused blank cards. */
    private function currentBatch(): ?CardStockBatch
    {
        return CardStockBatch::withCount('printJobs')->orderBy('received_on')->orderBy('id')->get()
            ->first(fn (CardStockBatch $b) => $b->print_jobs_count < $b->quantity);
    }
}
