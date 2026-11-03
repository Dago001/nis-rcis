<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardDistribution extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_DISPATCH = 'AWAITING_DISPATCH';
    public const STATUS_DISPATCHED = 'DISPATCHED';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_AVAILABLE_FOR_ISSUANCE = 'AVAILABLE_FOR_ISSUANCE';
    public const STATUS_ISSUED = 'ISSUED';

    protected $table = 'card_distributions';

    protected $fillable = [
        'card_id',
        'application_id',
        'card_number',
        'application_number',
        'destination_zone_id',
        'destination_command_id',
        'destination_center_id',
        'dispatch_reference',
        'status',
        'courier_service',
        'waybill_number',
        'dispatch_notes',
        'dispatched_by',
        'dispatched_at',
        'receipt_notes',
        'received_by',
        'received_at',
        'issued_by',
        'issued_at',
        'geofence_verified_receipt',
        'geofence_verified_issuance',
        'receipt_latitude',
        'receipt_longitude',
        'issuance_latitude',
        'issuance_longitude',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'issued_at' => 'datetime',
            'geofence_verified_receipt' => 'boolean',
            'geofence_verified_issuance' => 'boolean',
            'receipt_latitude' => 'float',
            'receipt_longitude' => 'float',
            'issuance_latitude' => 'float',
            'issuance_longitude' => 'float',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(NisZone::class, 'destination_zone_id');
    }

    public function destinationCommand(): BelongsTo
    {
        return $this->belongsTo(NisCommand::class, 'destination_command_id');
    }

    public function destinationCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class, 'destination_center_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeForStaffJurisdiction(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->isHeadquarters()) {
            return $query;
        }

        if ($user->isZonal()) {
            return $query->where('destination_zone_id', $user->zone_id);
        }

        if ($user->isCommand()) {
            return $query->where('destination_command_id', $user->command_id);
        }

        return $query;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_AWAITING_DISPATCH => 'Awaiting Dispatch',
            self::STATUS_DISPATCHED => 'Dispatched (In Transit)',
            self::STATUS_RECEIVED => 'Received at Command',
            self::STATUS_AVAILABLE_FOR_ISSUANCE => 'Available for Issuance',
            self::STATUS_ISSUED => 'Issued to Applicant',
            default => $this->status,
        };
    }
}
