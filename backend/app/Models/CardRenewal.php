<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardRenewal extends Model
{
    protected $fillable = [
        'card_id', 'renewal_number', 'from_date', 'to_date', 'renewed_at', 'endorsing_officer_id',
        'endorsing_officer', 'officer_service_no', 'fee_paid_kobo', 'receipt_number', 'remarks',
    ];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }
}
