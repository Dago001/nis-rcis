<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NisZone extends Model
{
    use HasFactory;

    protected $table = 'nis_zones';

    protected $fillable = [
        'code',
        'name',
        'headquarters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function commands(): HasMany
    {
        return $this->hasMany(NisCommand::class, 'zone_id')->orderBy('name');
    }

    public function enrollmentCenters(): HasMany
    {
        return $this->hasMany(EnrollmentCenter::class, 'zone_id')->orderBy('name');
    }
}
