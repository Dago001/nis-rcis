<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'application_id', 'draft_id', 'type', 'disk', 'path', 'original_name',
        'mime_type', 'size_bytes', 'sha256', 'version', 'is_current',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'is_current' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
