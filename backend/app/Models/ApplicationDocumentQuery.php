<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocumentQuery extends Model
{
    public const STATUS_QUERY_RAISED = 'QUERY_RAISED';
    public const STATUS_AWAITING_RESPONSE = 'AWAITING_RESPONSE';
    public const STATUS_RESPONSE_SUBMITTED = 'RESPONSE_SUBMITTED';
    public const STATUS_RESOLVED = 'RESOLVED';

    public const STAGE_VETTING = 'VETTING';
    public const STAGE_APPROVAL = 'APPROVAL';

    protected $fillable = [
        'application_id',
        'document_id',
        'document_type',
        'stage',
        'query_reason',
        'status',
        'queried_by',
        'queried_at',
        'applicant_response',
        'responded_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'replacement_document_id',
    ];

    protected function casts(): array
    {
        return [
            'queried_at' => 'datetime',
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'document_id');
    }

    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'replacement_document_id');
    }

    public function queriedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queried_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_QUERY_RAISED,
            self::STATUS_AWAITING_RESPONSE,
            self::STATUS_RESPONSE_SUBMITTED,
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUERY_RAISED => 'Query Raised',
            self::STATUS_AWAITING_RESPONSE => 'Awaiting Applicant Response',
            self::STATUS_RESPONSE_SUBMITTED => 'Response Submitted',
            self::STATUS_RESOLVED => 'Resolved',
            default => str_replace('_', ' ', $this->status),
        };
    }
}
