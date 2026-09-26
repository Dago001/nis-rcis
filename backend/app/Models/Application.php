<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    /** Particulars shared with ResidenceCard (copied onto the card at capture). */
    public const PARTICULARS = [
        'surname', 'forenames', 'nationality', 'date_of_birth', 'place_of_birth', 'sex', 'height',
        'complexion', 'eye_color', 'hair_color', 'distinguished_features', 'blood_group', 'profession',
        'domicile', 'domicile_state', 'domicile_lga', 'change_of_address', 'passport_number', 'passport_issue_date', 'passport_expiry',
        'national_id_number', 'tax_id_number', 'emergency_contact_name', 'emergency_contact_relation',
        'emergency_contact_phone', 'emergency_contact_address', 'emergency_contact_state', 'emergency_contact_lga',
    ];

    protected $guarded = ['id', 'status', 'application_number', 'reference_number'];

    protected $hidden = ['fingerprint_template', 'risk_flags'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'date_of_birth' => 'date:Y-m-d',
            'passport_issue_date' => 'date:Y-m-d',
            'passport_expiry' => 'date:Y-m-d',
            'appointment_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'biometrics_captured_at' => 'datetime',
            'ready_at' => 'datetime',
            'collected_at' => 'datetime',
            'risk_flags' => 'array',
            'risk_checked_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function enrollmentCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }

    public function renewalOfCard(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'renewal_of_card_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->where('is_current', true);
    }

    public function allDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function currentDocument(DocumentType $type): ?ApplicationDocument
    {
        return $this->documents->firstWhere('type', $type);
    }

    public function fullName(): string
    {
        return trim("{$this->forenames} {$this->surname}");
    }
}
