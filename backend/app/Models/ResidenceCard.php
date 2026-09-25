<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResidenceCard extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'status', 'card_number', 'verification_token'];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'date_of_birth' => 'date:Y-m-d',
            'passport_issue_date' => 'date:Y-m-d',
            'passport_expiry' => 'date:Y-m-d',
            'decision_date' => 'date:Y-m-d',
            'issued_on' => 'date:Y-m-d',
            'expires_on' => 'date:Y-m-d',
            'is_watchlisted' => 'boolean',
            'revoked_at' => 'datetime',
            'watchlisted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast() && ! $this->expires_on->isToday();
    }

    /**
     * Status shown to the public / partner verifiers.
     * VALID | EXPIRED | REVOKED | WATCHLISTED | NOT_ISSUED
     */
    public function verificationStatus(): string
    {
        return match (true) {
            $this->status === CardStatus::Revoked => 'REVOKED',
            $this->is_watchlisted => 'WATCHLISTED',
            ! $this->status->isActive() => 'NOT_ISSUED',
            $this->isExpired() => 'EXPIRED',
            default => 'VALID',
        };
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(CardRenewal::class, 'card_id')->orderBy('renewal_number');
    }

    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'card_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function issuingOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuing_officer_id');
    }

    public function fullName(): string
    {
        return trim("{$this->forenames} {$this->surname}");
    }
}
