<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPrintJob extends Model
{
    public const PRINTED = 'PRINTED';

    public const SPOILED = 'SPOILED';

    protected $guarded = ['id'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CardStockBatch::class, 'batch_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
