#!/bin/bash
set -e

cd /home/nimcosel/domains/rcis.nimcoselection.com/app/backend

echo "Updating NumberGenerator.php..."
cat << 'EOF' > app/Services/NumberGenerator.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Collision-free identifiers backed by PostgreSQL sequences.
 */
class NumberGenerator
{
    private function nextVal(string $seqName, int $defaultStart): int
    {
        if (DB::getDriverName() === 'pgsql') {
            return (int) DB::scalar("SELECT nextval('{$seqName}')");
        }

        // MySQL sequence simulation via atomic update
        return DB::transaction(function () use ($seqName, $defaultStart) {
            DB::table('system_sequences')->insertOrIgnore([
                'name' => $seqName,
                'current_val' => $defaultStart - 1,
            ]);

            DB::table('system_sequences')
                ->where('name', $seqName)
                ->increment('current_val', 1);

            return (int) DB::table('system_sequences')
                ->where('name', $seqName)
                ->value('current_val');
        });
    }

    /** e.g. RC-2026-100001 */
    public function applicationNumber(): string
    {
        do {
            $next = $this->nextVal('application_number_seq', 100001);
            $candidate = sprintf('RC-%s-%06d', now()->format('Y'), $next);
        } while (DB::table('applications')->where('application_number', $candidate)->exists());

        return $candidate;
    }

    /** Unguessable reference printed on slips (needed for public tracking). */
    public function referenceNumber(): string
    {
        return 'NIS-'.strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
    }

    /** e.g. 389108 (continues the legacy series) */
    public function cardNumber(): string
    {
        do {
            $candidate = (string) $this->nextVal('card_number_seq', 389108);
        } while (DB::table('residence_cards')->where('card_number', $candidate)->exists());

        return $candidate;
    }

    /** Legacy booklet format: RC-389108/26 */
    public function bookletNumber(string $cardNumber): string
    {
        return "RC-{$cardNumber}/".now()->format('y');
    }

    public function verificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function paymentReference(): string
    {
        return 'NISRC-'.now()->format('ymd').'-'.strtoupper(Str::random(10));
    }
}
EOF

echo "Updating DocumentType.php..."
cat << 'EOF' > app/Enums/DocumentType.php
<?php

namespace App\Enums;

enum DocumentType: string
{
    case Photo = 'photo';
    case Signature = 'signature';
    case PassportCopy = 'passport_copy';
    case ResidenceVisa = 'residence_visa';
    case QuotaApproval = 'quota_approval';
    case DomicileProof = 'domicile_proof';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Passport photograph',
            self::Signature => 'Signature',
            self::PassportCopy => 'International passport data page / National ID / ECOWAS Travel Certificate',
            self::ResidenceVisa => 'Subject-to-Regularization / residence visa',
            self::QuotaApproval => 'Expatriate quota approval',
            self::DomicileProof => 'Proof of domicile (Utility bill)',
            self::Additional => 'Additional document',
        };
    }

    /** Documents an applicant must upload before submitting. */
    public static function requiredForSubmission(): array
    {
        return [self::Photo, self::PassportCopy, self::DomicileProof];
    }

    /** Types applicants may upload (signature is captured at the desk). */
    public static function applicantUploadable(): array
    {
        return [self::Photo, self::PassportCopy, self::ResidenceVisa, self::QuotaApproval, self::DomicileProof, self::Additional];
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Photo, self::Signature => ['image/jpeg', 'image/png'],
            default => ['image/jpeg', 'image/png', 'application/pdf'],
        };
    }
}
EOF

echo "Updating ApplicationWorkflow.php..."
cat << 'EOF' > app/Services/ApplicationWorkflow.php
<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Notifications\ApplicationAwaitingReview;
use App\Notifications\ApplicationStatusChanged;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The single place where an application's status changes.
 */
class ApplicationWorkflow
{
    public function transition(
        Application $application,
        ApplicationStatus $to,
        Model $actor,
        ?string $notes = null,
        ?callable $mutate = null,
    ): Application {
        $application = DB::transaction(function () use ($application, $to, $actor, $notes, $mutate) {
            /** @var Application $locked */
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Application {$locked->application_number} is {$from->value}; it cannot move to {$to->value}.",
                ]);
            }

            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();

            $history = new ApplicationStatusHistory([
                'application_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'notes' => $notes,
            ]);
            $history->actor()->associate($actor);
            $history->save();

            Audit::log("APPLICATION_{$to->value}", "Application {$locked->application_number}: {$from->value} → {$to->value}", $locked,
                array_filter(['notes' => $notes]), $actor);

            return $locked;
        });

        $this->notify($application, $notes);

        return $application;
    }

    public function recordSubmission(Application $application, Model $actor): void
    {
        $history = new ApplicationStatusHistory([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::PendingApproval,
            'notes' => $application->channel === 'ASSISTED' ? 'Assisted application entered by staff' : 'Submitted online',
        ]);
        $history->actor()->associate($actor);
        $history->save();

        Audit::log('APPLICATION_SUBMITTED', "Application {$application->application_number} submitted ({$application->channel})", $application, actor: $actor);

        $this->notify($application, null);
    }

    private function notify(Application $application, ?string $notes): void
    {
        try {
            if ($application->applicant) {
                $application->applicant->notify(new ApplicationStatusChanged($application, $notes));
            }

            if ($application->status === ApplicationStatus::PendingApproval) {
                $reviewers = User::query()
                    ->where('is_active', true)
                    ->whereIn('role', [StaffRole::ApprovingOfficer, StaffRole::SuperAdmin])
                    ->get();
                Notification::send($reviewers, new ApplicationAwaitingReview($application));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Application workflow notification delivery failed: ' . $e->getMessage(), [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
            ]);
        }
    }
}
EOF

echo "Updating Application.php..."
cat << 'EOF' > app/Models/Application.php
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
            'decided_at' => 'datetime',
            'biometrics_captured_at' => 'datetime',
            'ready_at' => 'datetime',
            'collected_at' => 'datetime',
            'risk_flags' => 'array',
            'fingerprints' => 'encrypted:array',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

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

    public function hasUploadedAllRequiredDocuments(): bool
    {
        $uploaded = $this->documents->pluck('type')->all();

        foreach (DocumentType::requiredForSubmission() as $required) {
            if (! in_array($required, $uploaded, true)) {
                return false;
            }
        }

        return true;
    }
}
EOF

echo "Updating ApplicationRules.php..."
cat << 'EOF' > app/Support/ApplicationRules.php
<?php

namespace App\Support;

use App\Rules\NigerianLga;
use Illuminate\Validation\Rule;

class ApplicationRules
{
    public const NAME = "/^[\pL][\pL .'-]*$/u";
    public const PHONE = '/^\+[1-9][0-9]{6,14}$/';
    public const TEXT = "/^[\pL][\pL .,'()\/&-]*$/u";
    public const ADDRESS = "/^[\pL0-9][\pL0-9 .,'()\/#&:;-]*$/u";

    public static function particulars(?string $keepNationality = null): array
    {
        return [
            'surname' => ['required', 'string', 'max:100', 'regex:'.self::NAME],
            'forenames' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'nationality' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) use ($keepNationality) {
                $allowed = [...config('nis.ecowas_member_states'), ...config('nis.ecowas_legacy_names'), $keepNationality];
                $upperAllowed = array_map('strtoupper', array_filter($allowed));
                if (! in_array(strtoupper(trim((string) $value)), $upperAllowed, true)) {
                    $fail('The ECOWAS residence card is only for citizens of ECOWAS member states.');
                }
            }],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1900-01-01'],
            'place_of_birth' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'sex' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'height' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+(\.[0-9]{1,2})?\s?(m|cm|M|CM)?$/'],
            'complexion' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'eye_color' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'hair_color' => ['nullable', 'string', 'max:50', 'regex:'.self::NAME],
            'distinguished_features' => ['nullable', 'string', 'max:150', 'regex:'.self::TEXT],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UNKNOWN'])],
            'profession' => ['required', 'string', 'max:150', 'regex:'.self::TEXT],
            'home_country_address' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'domicile' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'domicile_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'domicile_lga' => ['required', 'string', new NigerianLga('domicile_state')],
            'change_of_address' => ['nullable', 'string', 'max:500', 'regex:'.self::ADDRESS],
            'passport_number' => ['required', 'string', 'regex:/^[A-Z0-9-]{4,20}$/i'],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry' => ['nullable', 'date'],
            'national_id_number' => ['nullable', 'string', 'regex:/^[0-9]{11}$/'],
            'tax_id_number' => ['nullable', 'string', 'regex:/^[0-9-]{6,20}$/'],
            'emergency_contact_name' => ['required', 'string', 'max:150', 'regex:'.self::NAME],
            'emergency_contact_relation' => ['required', 'string', 'max:50', 'regex:'.self::NAME],
            'emergency_contact_phone' => ['required', 'string', 'regex:'.self::PHONE],
            'emergency_contact_address' => ['required', 'string', 'max:300', 'regex:'.self::ADDRESS],
            'emergency_contact_state' => ['required', 'string', Rule::in(NigeriaLgas::states())],
            'emergency_contact_lga' => ['required', 'string', new NigerianLga('emergency_contact_state')],
        ];
    }

    public static function quota(): array
    {
        return [
            'quota_reference' => ['nullable', 'string', 'max:60', 'regex:#^[A-Z0-9][A-Z0-9/ .-]*$#'],
            'employer_name' => ['nullable', 'required_with:quota_reference', 'string', 'max:150', 'regex:'.self::TEXT],
        ];
    }

    public static function contact(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.self::PHONE],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public static function appointment(): array
    {
        return [
            'enrollment_center_id' => ['required', 'integer', Rule::exists('enrollment_centers', 'id')->where('is_active', true)],
            'appointment_date' => ['required', 'date', 'after:today', 'before:+60 days'],
            'appointment_time' => ['required', 'string', 'max:10'],
        ];
    }

    public static function normalise(array $data): array
    {
        $upperFields = [
            'surname', 'forenames', 'nationality', 'place_of_birth',
            'complexion', 'eye_color', 'hair_color', 'distinguished_features',
            'profession', 'home_country_address', 'domicile', 'change_of_address',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_address',
            'employer_name', 'passport_number',
        ];

        foreach ($upperFields as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = mb_strtoupper(trim($data[$f]), 'UTF-8');
            }
        }

        return $data;
    }
}
EOF

echo "Updating nis.php..."
cat << 'EOF' > config/nis.php
<?php

return [

    'organization' => 'Nigeria Immigration Service',
    'directorate' => 'Directorate of Visa and Residency',
    'issuing_country' => 'FEDERAL REPUBLIC OF NIGERIA',
    'approving_authority' => 'COMPTROLLER GENERAL OF IMMIGRATION',

    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    'fee_naira' => (int) env('RESIDENCE_CARD_FEE_NAIRA', 35000),
    'card_validity_years' => (int) env('CARD_VALIDITY_YEARS', 2),

    'sla_working_days' => (int) env('SLA_WORKING_DAYS', 10),
    'public_holidays' => array_filter(explode(',', (string) env('PUBLIC_HOLIDAYS', ''))),

    'integrations' => [
        'interpol_sltd' => [
            'driver' => env('INTERPOL_SLTD_DRIVER', 'simulated'),
            'url' => env('INTERPOL_SLTD_URL'),
            'api_key' => env('INTERPOL_SLTD_API_KEY'),
            'timeout' => (int) env('INTERPOL_SLTD_TIMEOUT', 10),
            'test_hits' => array_filter(explode(',', (string) env('INTERPOL_SLTD_TEST_HITS', ''))),
        ],
        'moi_quota' => [
            'driver' => env('MOI_QUOTA_DRIVER', 'simulated'),
            'url' => env('MOI_QUOTA_URL'),
            'api_key' => env('MOI_QUOTA_API_KEY'),
            'timeout' => (int) env('MOI_QUOTA_TIMEOUT', 10),
        ],
    ],

    'fingerprints_required' => (bool) env('FINGERPRINTS_REQUIRED', false),

    'ecowas_member_states' => ['BENIN', 'BURKINA FASO', 'BURKINA FASO (EXCEPTIONAL)', 'CABO VERDE', "COTE D'IVOIRE", 'GAMBIA', 'GHANA', 'GUINEA', 'GUINEA-BISSAU', 'LIBERIA', 'MALI', 'MALI (EXCEPTIONAL)', 'NIGER', 'NIGER (EXCEPTIONAL)', 'SENEGAL', 'SIERRA LEONE', 'TOGO'],
    'ecowas_legacy_names' => ['IVOIRE'],
    'ecowas_dialling_codes' => ['229', '226', '238', '225', '220', '233', '224', '245', '231', '223', '227', '234', '221', '232', '228'],

    'alerts' => [
        'emails' => array_filter(array_map('trim', explode(',', (string) env('ALERT_EMAILS', '')))),
        'backup_max_age_hours' => (int) env('ALERT_BACKUP_MAX_AGE_HOURS', 36),
    ],

    'storage_disk' => env('NIS_STORAGE_DISK', 'local'),
    'qr_secret' => env('NIS_QR_SECRET', 'nis-rcis-secret-key-change-me'),
];
EOF

echo "Optimizing framework..."
/opt/alt/php84/usr/bin/php artisan optimize

echo "=== ALL BACKEND FILES UPDATED SUCCESSFULLY ==="
