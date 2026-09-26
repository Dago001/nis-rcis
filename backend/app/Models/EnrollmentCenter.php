<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentCenter extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'state', 'address', 'daily_capacity', 'time_slots', 'is_active'];

    protected function casts(): array
    {
        return [
            'time_slots' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
