<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to refund the residence card fee (e.g. after a rejection).
 */
class Refund extends Model
{
    protected $fillable = [
        'payment_id', 'applicant_id', 'application_id', 'amount_kobo', 'reason', 'status',
        'decided_by', 'decided_at', 'decision_notes', 'gateway_reference', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
