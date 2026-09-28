<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date:Y-m-d',
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function enrollmentCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }
}
