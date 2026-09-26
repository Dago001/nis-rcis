<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'alerted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
