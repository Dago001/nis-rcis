<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedReportFilter extends Model
{
    protected $fillable = ['user_id', 'name', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
