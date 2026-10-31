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

    public function queries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApplicationDocumentQuery::class, 'document_id');
    }

    public function activeQuery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ApplicationDocumentQuery::class, 'document_id')
            ->where('status', '!=', ApplicationDocumentQuery::STATUS_RESOLVED)
            ->latestOfMany();
    }
}
