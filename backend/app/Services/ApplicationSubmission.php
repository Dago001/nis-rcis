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
use Illuminate\Support\Facades\DB;
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

        $missing = collect(DocumentType::requiredForSubmission())
            ->reject(fn (DocumentType $t) => $documents->contains('type', $t))
            ->map->label();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['documents' => 'Please upload: '.$missing->implode(', ').'.']);
        }

        $this->assertNoOpenApplication($applicant, $data['passport_number']);
        $renewalCard = $data['type'] === 'RENEWAL' ? $this->claimRenewalCard($applicant, $data) : null;

        return $this->screen(DB::transaction(function () use ($applicant, $data, $draft, $documents, $renewalCard) {
            $payment = Payment::where('reference', $data['payment_reference'])->lockForUpdate()->first();

            if (! $payment || $payment->applicant_id !== $applicant->id || ! $payment->isSuccessful()) {
                throw ValidationException::withMessages(['payment_reference' => 'Payment has not been confirmed. Complete payment before submitting.']);
            }
            if ($payment->application_id !== null) {
                throw ValidationException::withMessages(['payment_reference' => 'This payment has already been used for another application.']);
            }
            if ($payment->amount_kobo < $this->feeKobo()) {
                throw ValidationException::withMessages(['payment_reference' => 'The amount paid does not cover the residence card fee.']);
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

    /** Fraud and duplicate checks, run after the application is saved. */
    private function screen(Application $application): Application
    {
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

    public function feeKobo(): int
    {
        return config('nis.fee_naira') * 100;
    }

    private function create(array $data, string $channel, ?Applicant $applicant, ?User $officer, ?ResidenceCard $renewalCard, string $paymentStatus): Application
    {
        $center = EnrollmentCenter::findOrFail($data['enrollment_center_id']);
        $this->scheduler->assertBookable($center, $data['appointment_date'], $data['appointment_time']);

        $application = new Application([
            ...collect($data)->only([...Application::PARTICULARS, 'phone', 'email', 'enrollment_center_id', 'appointment_date', 'appointment_time'])->all(),
            'applicant_id' => $applicant?->id,
            'type' => $renewalCard ? 'RENEWAL' : 'NEW',
            'renewal_of_card_id' => $renewalCard?->id,
            'channel' => $channel,
            'created_by' => $officer?->id,
            'fee_amount_kobo' => $this->feeKobo(),
            'payment_status' => $paymentStatus,
            'submitted_at' => now(),
        ]);
        $application->application_number = $this->numbers->applicationNumber();
        $application->reference_number = $this->numbers->referenceNumber();
        $application->status = ApplicationStatus::PendingApproval;
        $application->save();

        return $application->setRelation('applicant', $applicant);
    }

    private function assertNoOpenApplication(Applicant $applicant, string $passport): void
    {
        $open = Application::query()
            ->where(fn ($q) => $q->where('applicant_id', $applicant->id)->orWhere('passport_number', $passport))
            ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Issued])
            ->first();

        if ($open) {
            throw ValidationException::withMessages([
                'passport_number' => "Application {$open->application_number} for this passport is still being processed.",
            ]);
        }
    }

    /**
     * A renewal may only be made for the applicant's own card. Cards issued
     * before the portal existed (no applicant linked) can be claimed by
     * proving both the card number and the passport number on it.
     */
    private function claimRenewalCard(Applicant $applicant, array $data): ResidenceCard
    {
        $card = ResidenceCard::where('card_number', $data['renewal_card_number'] ?? '')->first();

        $owns = $card && ($card->applicant_id === $applicant->id
            || ($card->applicant_id === null && $card->passport_number === $data['passport_number']));

        if (! $owns) {
            throw ValidationException::withMessages(['renewal_card_number' => 'No residence card with this number is registered to you and this passport.']);
        }
        if ($card->status === CardStatus::Revoked || $card->is_watchlisted) {
            throw ValidationException::withMessages(['renewal_card_number' => 'This card cannot be renewed online. Please visit an NIS office.']);
        }

        if ($card->applicant_id === null) {
            $card->forceFill(['applicant_id' => $applicant->id])->save();
        }

        return $card;
    }
}
