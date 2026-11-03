<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NisOfficeLocation extends Model
{
    use HasFactory;

    protected $table = 'nis_office_locations';

    protected $fillable = [
        'name',
        'code',
        'zone_id',
        'command_id',
        'enrollment_center_id',
        'address',
        'state',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'enforce_biometrics',
        'enforce_card_receipt',
        'enforce_card_issuance',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
            'enforce_biometrics' => 'boolean',
            'enforce_card_receipt' => 'boolean',
            'enforce_card_issuance' => 'boolean',
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

    public function enrollmentCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class, 'enrollment_center_id');
    }
}
