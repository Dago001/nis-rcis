<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sensitive card action waiting for a second officer (two-person rule).
 */
class ApprovalRequest extends Model
{
    public const REVOKE = 'CARD_REVOKE';

    public const REINSTATE = 'CARD_REINSTATE';

    public const UPDATE = 'CARD_UPDATE';

    protected $fillable = ['action', 'card_id', 'payload', 'reason', 'requested_by', 'status', 'decided_by', 'decided_at', 'decision_notes'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'decided_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function label(): string
    {
        return match ($this->action) {
            self::REVOKE => 'Revoke card',
            self::REINSTATE => 'Reinstate card',
            self::UPDATE => 'Edit card particulars',
            default => $this->action,
        };
    }
}
