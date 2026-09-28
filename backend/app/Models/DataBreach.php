<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NDPA 2023 personal-data breach register. The NDPC must be told within
 * 72 hours of the Service becoming aware of a notifiable breach.
 */
class DataBreach extends Model
{
    protected $fillable = [
        'title', 'description', 'severity', 'status', 'occurred_at', 'detected_at', 'data_categories',
        'affected_count', 'containment_actions', 'regulator_notified_at', 'subjects_notified_at', 'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime', 'detected_at' => 'datetime',
            'regulator_notified_at' => 'datetime', 'subjects_notified_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
