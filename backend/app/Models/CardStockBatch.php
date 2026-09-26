<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardStockBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(CardPrintJob::class, 'batch_id');
    }
}
