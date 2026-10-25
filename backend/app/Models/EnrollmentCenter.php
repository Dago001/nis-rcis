<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'state',
        'address',
        'zone_id',
        'command_id',
        'zone_code',
        'zone_name',
        'command_name',
        'daily_capacity',
        'time_slots',
        'is_active',
        'is_biometric_enabled',
        'is_booking_enabled',
    ];

    protected function casts(): array
    {
        return [
            'time_slots' => 'array',
            'is_active' => 'boolean',
            'is_biometric_enabled' => 'boolean',
            'is_booking_enabled' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(NisZone::class, 'zone_id');
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(NisCommand::class, 'command_id');
    }
}
