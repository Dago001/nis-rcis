<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationDraft extends Model
{
    protected $fillable = ['applicant_id', 'type', 'current_step', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'draft_id')->where('is_current', true);
    }
}
