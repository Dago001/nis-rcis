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
        'home_country_address', 'domicile', 'domicile_state', 'domicile_lga', 'change_of_address', 'passport_number', 'passport_issue_date', 'passport_expiry',
        'national_id_number', 'tax_id_number', 'emergency_contact_name', 'emergency_contact_relation',
        'emergency_contact_phone', 'emergency_contact_address', 'emergency_contact_state', 'emergency_contact_lga',
        'emergency_contact_id_type',
        'home_country_emergency_contact_name', 'home_country_emergency_contact_relation',
        'home_country_emergency_contact_phone', 'home_country_emergency_contact_address',
        'home_country_emergency_contact_id_type',
    ];

    protected $guarded = ['id', 'status', 'application_number', 'reference_number'];

    protected $hidden = ['fingerprint_template', 'fingerprints', 'risk_flags'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'date_of_birth' => 'date:Y-m-d',
            'passport_issue_date' => 'date:Y-m-d',
            'passport_expiry' => 'date:Y-m-d',
            'appointment_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'vetted_at' => 'datetime',
            'decided_at' => 'datetime',
            'biometrics_captured_at' => 'datetime',
            'ready_at' => 'datetime',
            'collected_at' => 'datetime',
            'risk_flags' => 'array',
            'fingerprints' => 'encrypted:array',
            'risk_checked_at' => 'datetime',
            'has_cod' => 'boolean',
            'cod_changes' => 'array',
            'cod_fee_kobo' => 'integer',
        ];
    }

    public function fromEncryptedString($value)
    {
        try {
            return parent::fromEncryptedString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            \Illuminate\Support\Facades\Log::warning("Corrupted encrypted attribute on application {$this->id}: {$e->getMessage()}");
            return null;
        }
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function enrollmentCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class);
    }

    public function actualEnrollmentCenter(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class, 'actual_enrollment_center_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'card_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** The account holder's own application, when this one is for a spouse or child. */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'principal_application_id');
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Application::class, 'principal_application_id');
    }

    public function renewalOfCard(): BelongsTo
    {
        return $this->belongsTo(ResidenceCard::class, 'renewal_of_card_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function vettedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vetted_by');
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

    public function getCategoryAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return strtoupper($value);
        }

        // Infer from documents if column is empty on older records
        if ($this->relationLoaded('documents')) {
            $docs = $this->documents;
            if ($docs->contains('type', DocumentType::AdmissionLetter) || $docs->contains('type', DocumentType::AcceptanceLetter) || $docs->contains('type', DocumentType::ProofOfPayment)) {
                return 'STUDENT';
            }
            if ($docs->contains('type', DocumentType::CacCertificate)) {
                return 'BUSINESS';
            }
            if ($docs->contains('type', DocumentType::AppointmentLetter)) {
                return 'WORK';
            }
            if ($docs->contains('type', DocumentType::ReligiousInvitation) || $docs->contains('type', DocumentType::ReligiousRegistration)) {
                return 'RELIGIOUS';
            }
        }

        return null;
    }

    public function getVettingStatusAttribute(): string
    {
        if ($this->vetted_at !== null || in_array($this->status, [
            ApplicationStatus::Vetted,
            ApplicationStatus::ApprovedForBiometrics,
            ApplicationStatus::BiometricsCaptured,
            ApplicationStatus::ReadyForCollection,
            ApplicationStatus::Issued,
        ], true)) {
            return 'VETTED';
        }

        if ($this->status === ApplicationStatus::Queried && $this->vetted_at === null) {
            return 'QUERIED';
        }

        if ($this->status === ApplicationStatus::Rejected && $this->vetted_at === null) {
            return 'REJECTED';
        }

        return 'PENDING_APPROVAL';
    }

    public function getVettingStatusLabelAttribute(): string
    {
        return match ($this->vetting_status) {
            'VETTED' => 'Vetted',
            'QUERIED' => 'Queried',
            'REJECTED' => 'Rejected',
            default => 'Awaiting Vetting',
        };
    }

    public function getApprovalStatusAttribute(): ?string
    {
        if (in_array($this->status, [
            ApplicationStatus::ApprovedForBiometrics,
            ApplicationStatus::BiometricsCaptured,
            ApplicationStatus::ReadyForCollection,
            ApplicationStatus::Issued,
        ], true) || ($this->decided_at !== null && ! in_array($this->status, [ApplicationStatus::Queried, ApplicationStatus::Rejected], true))) {
            return 'APPROVED';
        }

        if ($this->status === ApplicationStatus::Queried && $this->vetted_at !== null) {
            return 'QUERIED';
        }

        if ($this->status === ApplicationStatus::Rejected && $this->vetted_at !== null) {
            return 'REJECTED';
        }

        if ($this->status === ApplicationStatus::Vetted || $this->vetted_at !== null) {
            return 'AWAITING_APPROVAL';
        }

        return null;
    }

    public function getApprovalStatusLabelAttribute(): ?string
    {
        return match ($this->approval_status) {
            'APPROVED' => 'Approved',
            'AWAITING_APPROVAL' => 'Awaiting Approval',
            'QUERIED' => 'Queried',
            'REJECTED' => 'Rejected',
            default => null,
        };
    }
}
