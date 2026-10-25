<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NisCommand extends Model
{
    use HasFactory;

    protected $table = 'nis_commands';

    protected $fillable = [
        'zone_id',
        'code',
        'name',
        'state',
        'location',
        'is_biometric_enabled',
        'is_booking_enabled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_biometric_enabled' => 'boolean',
            'is_booking_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(NisZone::class, 'zone_id');
    }

    public function enrollmentCenter(): HasOne
    {
        return $this->hasOne(EnrollmentCenter::class, 'command_id');
    }
}
