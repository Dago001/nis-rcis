<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Enums\DocumentType;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\EnrollmentCenter;
use App\Models\Payment;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Support\ApplicationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates applications in PENDING_APPROVAL, for both channels:
 *  - ONLINE: applicant portal, from the saved draft, after verified payment
 *  - ASSISTED: staff-entered walk-in application (legacy new-card.php)
 */
class ApplicationSubmission
{
    public function __construct(
        private NumberGenerator $numbers,
        private AppointmentScheduler $scheduler,
        private ApplicationWorkflow $workflow,
    ) {}

    public function submitOnline(Applicant $applicant, array $data): Application
    {
        $draft = $applicant->draft()->with('documents')->first();
        $documents = $draft?->documents ?? collect();

        if (empty($data['type'])) {
            $data['type'] = $draft?->type ?: 'NEW';
        }

        $category = strtoupper($data['category'] ?? '');
        if (! in_array($category, ['WORK', 'BUSINESS', 'STUDENT', 'RELIGIOUS'], true)) {
            $draftCategory = strtoupper($draft?->data['category'] ?? '');
            if (in_array($draftCategory, ['WORK', 'BUSINESS', 'STUDENT', 'RELIGIOUS'], true)) {
                $category = $draftCategory;
            } elseif ($documents->contains('type', DocumentType::AdmissionLetter)) {
                $category = 'STUDENT';
            } elseif ($documents->contains('type', DocumentType::CacCertificate)) {
                $category = 'BUSINESS';
            } elseif ($documents->contains('type', DocumentType::AppointmentLetter)) {
                $category = 'WORK';
            } elseif ($documents->contains('type', DocumentType::ReligiousInvitation) || $documents->contains('type', DocumentType::ReligiousRegistration)) {
                $category = 'RELIGIOUS';
            } else {
                $category = 'WORK';
            }
        }
        $data['category'] = $category;

        $requiredTypes = DocumentType::requiredForSubmission();
        if ($category === 'BUSINESS') {
            $requiredTypes[] = DocumentType::CacCertificate;
        } elseif ($category === 'STUDENT') {
            $requiredTypes[] = DocumentType::AdmissionLetter;
        } elseif ($category === 'WORK') {
            $requiredTypes[] = DocumentType::AppointmentLetter;
        } elseif ($category === 'RELIGIOUS') {
            $requiredTypes[] = DocumentType::ReligiousInvitation;
            $requiredTypes[] = DocumentType::ReligiousRegistration;
        }

        $missing = collect($requiredTypes)
            ->reject(fn (DocumentType $t) => $documents->contains('type', $t))
            ->map->label();
        if ($missing->isNotEmpty()) {
            if ($missing->count() === 1) {
                throw ValidationException::withMessages(['documents' => 'Please upload '.$missing->first().'.']);
            }
            throw ValidationException::withMessages(['documents' => 'Please upload: '.$missing->implode(', ').'.']);
        }

        $principal = ! empty($data['principal_application_id']) ? $this->principal($applicant, $data) : null;
        $renewalCard = in_array($data['type'], ['RENEWAL', 'REPLACE'], true) ? $this->claimRenewalCard($applicant, $data) : null;
        $this->assertNoOpenApplication($applicant, $data['passport_number'], $principal !== null, $renewalCard);

        $hasCod = ! empty($data['has_cod']) || ! empty($draft?->data['has_cod']);
        $requiredFeeKobo = $this->feeKobo($data, $hasCod);

        return $this->screen(DB::transaction(function () use ($applicant, $data, $draft, $documents, $renewalCard, $requiredFeeKobo) {
            $payment = Payment::where('reference', $data['payment_reference'])->lockForUpdate()->first();

            if (! $payment || $payment->applicant_id !== $applicant->id || ! $payment->isSuccessful()) {
                throw ValidationException::withMessages(['payment_reference' => 'Payment has not been confirmed. Complete payment before submitting.']);
            }
            if ($payment->application_id !== null) {
                throw ValidationException::withMessages(['payment_reference' => 'This payment has already been used for another application.']);
            }
            if ($payment->amount_kobo < $requiredFeeKobo) {
                throw ValidationException::withMessages(['payment_reference' => 'The amount paid does not cover the required application and administrative fees.']);
            }

            $application = $this->create($data, 'ONLINE', $applicant, null, $renewalCard, 'PAID');

            $payment->update(['application_id' => $application->id]);

            ApplicationDocument::whereIn('id', $documents->pluck('id'))
                ->update(['application_id' => $application->id, 'draft_id' => null]);
            $application->photo_path = $documents->firstWhere('type', DocumentType::Photo)?->path;
            $application->save();

            $draft?->delete();

            $this->workflow->recordSubmission($application, $applicant);

            return $application;
        }));
    }

    /** Fraud, duplicate and external (Interpol, Ministry of Interior) checks, run after the application is saved. */
    private function screen(Application $application): Application
    {
        app(IntegrationChecks::class)->run($application);
        app(RiskChecker::class)->check($application);

        return $application;
    }

    public function submitAssisted(User $officer, array $data): Application
    {
        return $this->screen(DB::transaction(function () use ($officer, $data) {
            $application = $this->create($data, 'ASSISTED', null, $officer, null, $data['payment_status']);
            $this->workflow->recordSubmission($application, $officer);

            return $application;
        }));
    }

    public function feeKobo(?array $draftData = null, bool $hasCod = false): int
    {
        $baseFee = (int) config('nis.fee_naira', 35000) * 100;
        $codActive = $hasCod || ! empty($draftData['has_cod']);
        if ($codActive) {
            $codFee = (int) config('nis.cod_fee_naira', 15000) * 100;
            return $baseFee + $codFee;
        }

        return $baseFee;
    }

    /**
     * Validate the applicant's draft particulars and required documents
     * BEFORE payment can be initialized.
     */
    public function assertPrePaymentValid(Applicant $applicant): void
    {
        $draft = $applicant->draft()->with('documents')->first();
        if (! $draft) {
            throw ValidationException::withMessages(['general' => 'No active application draft found. Please complete the application particulars first.']);
        }

        $data = $draft->data ?? [];
        if (empty($data['type'])) {
            $data['type'] = $draft->type ?: 'NEW';
        }
        $data = ApplicationRules::normalise(collect($data)->except(['phone', 'email'])->all());

        // Validate particulars
        $rules = ApplicationRules::particulars();
        if (! empty($data['principal_application_id'])) {
            if (($data['dependant_relationship'] ?? '') === 'CHILD') {
                $rules['date_of_birth'] = ['required', 'date', 'before:today', 'after:1900-01-01'];
            }
            $rules['phone'] = ['nullable', 'string', 'max:20', 'regex:'.ApplicationRules::PHONE];
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        $validator = Validator::make($data, [
            ...$rules,
            ...ApplicationRules::quota(),
            'type' => ['required', Rule::in(['NEW', 'RENEWAL', 'REPLACE'])],
            'renewal_card_number' => ['required_if:type,RENEWAL,REPLACE', 'nullable', 'string', 'max:20'],
        ], [], [
            'type' => 'application type',
            'renewal_card_number' => 'previous residence card number',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $principal = ! empty($data['principal_application_id']) ? $this->principal($applicant, $data) : null;
        $renewalCard = in_array($data['type'], ['RENEWAL', 'REPLACE'], true) ? $this->claimRenewalCard($applicant, $data) : null;
        $this->assertNoOpenApplication($applicant, $data['passport_number'], $principal !== null, $renewalCard);

        $hasCod = ! empty($data['has_cod']) || ! empty($draft->data['has_cod']);
        if ($data['type'] === 'RENEWAL') {
            if ($hasCod) {
                $codChanges = $data['cod_changes'] ?? $draft->data['cod_changes'] ?? [];
                if (empty($codChanges) || ! is_array($codChanges)) {
                    throw ValidationException::withMessages(['cod' => 'Please select at least one field to change and specify the correction.']);
                }
                foreach ($codChanges as $field => $change) {
                    $reqVal = trim((string) ($change['requested_value'] ?? $change['requested'] ?? ''));
                    $reason = trim((string) ($change['reason'] ?? ''));
                    if ($reqVal === '') {
                        throw ValidationException::withMessages(['cod' => "Please enter the requested new value for {$field}."]);
                    }
                    if ($reason === '') {
                        throw ValidationException::withMessages(['cod' => "Please state the reason for changing {$field}."]);
                    }
                }
            } else {
                $sourceApp = null;
                if (! empty($data['renewal_source_application_id'])) {
                    $sourceApp = Application::whereKey($data['renewal_source_application_id'])->where('applicant_id', $applicant->id)->first();
                } elseif ($renewalCard?->application) {
                    $sourceApp = $renewalCard->application;
                }
                if ($sourceApp) {
                    $protected = ['surname', 'forenames', 'nationality', 'date_of_birth', 'place_of_birth', 'sex', 'passport_number', 'passport_expiry'];
                    foreach ($protected as $f) {
                        $curVal = trim((string) ($data[$f] ?? ''));
                        $origVal = trim((string) ($sourceApp->{$f} instanceof \Carbon\CarbonInterface ? $sourceApp->{$f}->format('Y-m-d') : ($sourceApp->{$f} ?? '')));
                        if ($curVal !== '' && $origVal !== '' && strcasecmp($curVal, $origVal) !== 0) {
                            throw ValidationException::withMessages([
                                $f => "Protected personal information ({$f}) cannot be altered during ordinary renewal. Please select Change of Data (COD) to request this correction.",
                            ]);
                        }
                    }
                }
            }
        }

        // Validate required documents
        $documents = $draft->documents ?? collect();
        $category = strtoupper($data['category'] ?? '');
        if (! in_array($category, ['WORK', 'BUSINESS', 'STUDENT', 'RELIGIOUS'], true)) {
            $draftCategory = strtoupper($draft->data['category'] ?? '');
            if (in_array($draftCategory, ['WORK', 'BUSINESS', 'STUDENT', 'RELIGIOUS'], true)) {
                $category = $draftCategory;
            } elseif ($documents->contains('type', DocumentType::AdmissionLetter)) {
                $category = 'STUDENT';
            } elseif ($documents->contains('type', DocumentType::CacCertificate)) {
                $category = 'BUSINESS';
            } elseif ($documents->contains('type', DocumentType::AppointmentLetter)) {
                $category = 'WORK';
            } elseif ($documents->contains('type', DocumentType::ReligiousInvitation) || $documents->contains('type', DocumentType::ReligiousRegistration)) {
                $category = 'RELIGIOUS';
            } else {
                $category = 'WORK';
            }
        }

        $requiredTypes = DocumentType::requiredForSubmission();
        if ($category === 'BUSINESS') {
            $requiredTypes[] = DocumentType::CacCertificate;
        } elseif ($category === 'STUDENT') {
            $requiredTypes[] = DocumentType::AdmissionLetter;
        } elseif ($category === 'WORK') {
            $requiredTypes[] = DocumentType::AppointmentLetter;
        } elseif ($category === 'RELIGIOUS') {
            $requiredTypes[] = DocumentType::ReligiousInvitation;
            $requiredTypes[] = DocumentType::ReligiousRegistration;
        }

        $missing = collect($requiredTypes)
            ->reject(fn (DocumentType $t) => $documents->contains('type', $t))
            ->map->label();
        if ($missing->isNotEmpty()) {
            if ($missing->count() === 1) {
                throw ValidationException::withMessages(['documents' => 'Please upload '.$missing->first().'.']);
            }
            throw ValidationException::withMessages(['documents' => 'Please upload: '.$missing->implode(', ').'.']);
        }
    }

    private function create(array $data, string $channel, ?Applicant $applicant, ?User $officer, ?ResidenceCard $renewalCard, string $paymentStatus): Application
    {
        $center = EnrollmentCenter::findOrFail($data['enrollment_center_id']);
        $this->scheduler->assertBookable($center, $data['appointment_date'], $data['appointment_time']);

        $hasCod = ! empty($data['has_cod']);
        $codFeeKobo = $hasCod ? ((int) config('nis.cod_fee_naira', 15000) * 100) : 0;
        $totalFeeKobo = $this->feeKobo($data, $hasCod);

        $application = new Application([
            ...collect($data)->only([...Application::PARTICULARS, 'phone', 'email', 'enrollment_center_id', 'appointment_date', 'appointment_time', 'quota_reference', 'employer_name'])->all(),
            'category' => strtoupper($data['category'] ?? 'WORK'),
            'applicant_id' => $applicant?->id,
            'type' => in_array($data['type'] ?? '', ['RENEWAL', 'REPLACE'], true) ? $data['type'] : 'NEW',
            'renewal_of_card_id' => $renewalCard?->id,
            'principal_application_id' => $data['principal_application_id'] ?? null,
            'dependant_relationship' => ! empty($data['principal_application_id']) ? $data['dependant_relationship'] : null,
            'channel' => $channel,
            'created_by' => $officer?->id,
            'fee_amount_kobo' => $totalFeeKobo,
            'has_cod' => $hasCod,
            'cod_fee_kobo' => $codFeeKobo,
            'cod_changes' => $hasCod ? ($data['cod_changes'] ?? null) : null,
            'cod_status' => $hasCod ? 'PENDING' : null,
            'renewal_source_application_id' => $data['renewal_source_application_id'] ?? null,
            'payment_status' => $paymentStatus,
            'submitted_at' => now(),
        ]);
        $application->application_number = $this->numbers->applicationNumber();
        $application->reference_number = $this->numbers->referenceNumber();
        $application->status = ApplicationStatus::PendingApproval;
        $application->save();

        return $application->setRelation('applicant', $applicant);
    }

    /**
     * One open application per person: the account holder's own, or a
     * dependant's (checked by passport, since one account may apply for a
     * spouse and children at the same time).
     */
    private function assertNoOpenApplication(Applicant $applicant, string $passport, bool $forDependant = false, ?ResidenceCard $renewalCard = null): void
    {
        $query = Application::query()
            ->where(fn ($q) => $forDependant
                ? $q->where('passport_number', $passport)
                : $q->where(fn ($own) => $own->where('applicant_id', $applicant->id)->whereNull('principal_application_id'))->orWhere('passport_number', $passport))
            ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Issued]);

        // When renewing or replacing an existing card, exclude the application that generated this card
        if ($renewalCard) {
            $ignoreAppId = $renewalCard->application?->id;
            if ($ignoreAppId) {
                $query->where('id', '!=', $ignoreAppId);
            }
            $query->where(function ($q) use ($renewalCard) {
                $q->whereNull('card_id')->orWhere('card_id', '!=', $renewalCard->id);
            });
        }

        // Applications with an active/issued card already produced are complete and do not block renewal/replacement
        $query->whereDoesntHave('card', function ($cardQuery) {
            $cardQuery->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]);
        });

        $open = $query->first();

        if ($open) {
            throw ValidationException::withMessages([
                'passport_number' => 'This passport number is already associated with an existing record. Please review your details or contact support if you believe this is an error.',
            ]);
        }
    }

    /**
     * A dependant (spouse or child) is linked to the account holder's own
     * application, which must not have been rejected.
     */
    private function principal(Applicant $applicant, array $data): Application
    {
        $principal = Application::whereKey($data['principal_application_id'])
            ->where('applicant_id', $applicant->id)->whereNull('principal_application_id')->first();

        if (! $principal || $principal->status === ApplicationStatus::Rejected) {
            throw ValidationException::withMessages(['principal_application_id' => 'Choose one of your own applications that has not been rejected.']);
        }
        if ($principal->passport_number === $data['passport_number']) {
            throw ValidationException::withMessages(['passport_number' => 'A dependant must apply with their own passport.']);
        }

        return $principal;
    }

    /**
     * A renewal may only be made for the applicant's own card. Cards issued
     * before the portal existed (no applicant linked) can be claimed by
     * proving both the card number and the passport number on it.
     */
    private function claimRenewalCard(Applicant $applicant, array $data): ResidenceCard
    {
        $card = ResidenceCard::where('card_number', $data['renewal_card_number'] ?? '')->first();

        if (! $card && ! empty($data['renewal_source_application_id'])) {
            $sourceApp = Application::find($data['renewal_source_application_id']);
            if ($sourceApp && ($sourceApp->applicant_id === $applicant->id || strtolower((string)$sourceApp->email) === strtolower($applicant->email))) {
                $card = $sourceApp->card;
                if (! $card) {
                    $card = ResidenceCard::create([
                        'application_id' => $sourceApp->id,
                        'applicant_id' => $applicant->id,
                        'card_number' => $data['renewal_card_number'] ?: ('RC' . str_pad((string)$sourceApp->id, 8, '0', STR_PAD_LEFT)),
                        'surname' => $sourceApp->surname,
                        'forenames' => $sourceApp->forenames,
                        'nationality' => $sourceApp->nationality,
                        'date_of_birth' => $sourceApp->date_of_birth,
                        'sex' => $sourceApp->sex,
                        'passport_number' => $sourceApp->passport_number,
                        'category' => $sourceApp->category,
                        'email' => $sourceApp->email,
                        'phone' => $sourceApp->phone,
                        'issued_on' => now()->subYear(),
                        'expires_on' => now()->addYears(2),
                        'status' => CardStatus::Issued,
                    ]);
                }
            }
        }

        $owns = $card && ($card->applicant_id === $applicant->id
            || strtolower((string) $card->email) === strtolower($applicant->email)
            || ($card->application && ($card->application->applicant_id === $applicant->id || strtolower((string) $card->application->email) === strtolower($applicant->email)))
            || ($card->applicant_id === null && ($card->passport_number === $data['passport_number'] || strtolower((string)$card->email) === strtolower($applicant->email))));

        if (! $owns) {
            throw ValidationException::withMessages(['renewal_card_number' => 'No residence card with this number is registered to you and this passport.']);
        }
        if ($card->status === CardStatus::Revoked || $card->is_watchlisted) {
            throw ValidationException::withMessages(['renewal_card_number' => 'This card cannot be renewed online. Please visit an NIS office.']);
        }
        if ($data['type'] === 'REPLACE' && ! $card->reported_lost_at) {
            throw ValidationException::withMessages(['renewal_card_number' => 'Report this card lost or stolen before applying for a replacement.']);
        }
        if ($data['type'] === 'RENEWAL' && $card->reported_lost_at) {
            throw ValidationException::withMessages(['renewal_card_number' => 'This card was reported lost or stolen. Apply for a replacement instead.']);
        }

        if ($card->applicant_id === null) {
            $card->forceFill(['applicant_id' => $applicant->id])->save();
        }

        return $card;
    }
}
