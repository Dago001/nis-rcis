<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'applicant_id', 'application_id', 'provider', 'reference', 'amount_kobo', 'currency',
        'status', 'channel', 'paid_at', 'verified_at', 'gateway_response',
    ];

    protected $hidden = ['gateway_response'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCESS' && $this->verified_at !== null;
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
