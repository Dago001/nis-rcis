<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Enums\DocumentType;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\CardRenewal;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Card lifecycle operations (legacy biometrics-capture.php, card-details.php
 * and renew-card.php), each one transactional and audited.
 */
class CardIssuance
{
    public function __construct(
        private NumberGenerator $numbers,
        private DocumentStorage $storage,
        private ApplicationWorkflow $workflow,
    ) {}

    /**
     * Biometrics desk: capture live photo + signature, create the card
     * (status APPROVED = awaiting final approval) and move the application
     * to BIOMETRICS_CAPTURED.
     */
    public function captureBiometrics(Application $application, User $officer, array $input): Application
    {
        if ($application->status !== ApplicationStatus::ApprovedForBiometrics) {
            throw ValidationException::withMessages(['status' => "Application {$application->application_number} is not approved for biometrics."]);
        }

        $isSimulation = ! empty($input['simulation']);
        if ($isSimulation && app()->isProduction()) {
            throw new \DomainException('Biometrics simulation mode is strictly prohibited in production environments.');
        }

        $photo = $this->storage->storeDataUrl($input['photo'], DocumentType::Photo, ['application_id' => $application->id], $officer);
        $signature = $this->storage->storeDataUrl($input['signature'], DocumentType::Signature, ['application_id' => $application->id], $officer);

        $transitionNote = $isSimulation
            ? 'Biometrics captured (Development Simulation Mode) at '.($input['issued_at'] ?? $application->enrollmentCenter?->name)
            : 'Biometrics captured at '.($input['issued_at'] ?? $application->enrollmentCenter?->name);

        return $this->workflow->transition(
            $application,
            ApplicationStatus::BiometricsCaptured,
            $officer,
            $transitionNote,
            function (Application $locked) use ($officer, $input, $photo, $signature, $isSimulation) {
                $cardNumber = $this->numbers->cardNumber();
                $issuedOn = now();

                $card = new ResidenceCard([
                    ...$locked->only(Application::PARTICULARS),
                    'booklet_number' => $this->numbers->bookletNumber($cardNumber),
                    'issuing_country' => config('nis.issuing_country'),
                    'approving_authority' => config('nis.approving_authority'),
                    'statutory_protocol' => $isSimulation ? 'DEVELOPMENT_SIMULATION' : '',
                    'decision_reference' => $locked->application_number,
                    'decision_date' => $locked->decided_at ?? now(),
                    'photo_path' => $photo->path,
                    'signature_path' => $signature->path,
                    'issuing_officer_id' => $officer->id,
                    'issuing_officer_name' => mb_strtoupper($officer->fullname),
                    'issuing_officer_service_no' => $officer->service_number,
                    'issued_on' => $issuedOn->toDateString(),
                    'issued_at' => mb_strtoupper($input['issued_at'] ?? $locked->enrollmentCenter?->name ?? $officer->command),
                    'expires_on' => $issuedOn->copy()->addYears(config('nis.card_validity_years'))->subDay()->toDateString(),
                    'authority_signature' => 'COMPTROLLER GENERAL',
                    'created_by' => $officer->id,
                    'applicant_id' => $locked->applicant_id,
                ]);
                $card->card_number = $cardNumber;
                $card->verification_token = $this->numbers->verificationToken();
                $card->status = CardStatus::Approved;
                $card->save();

                $fingerprints = $isSimulation ? [
                    [
                        'status' => 'NOT_CAPTURED',
                        'reason' => 'Not Captured — Development Simulation',
                        'device' => 'Development Simulation',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ] : ($input['fingerprints'] ?? null);

                $locked->fill([
                    'photo_path' => $photo->path,
                    'signature_path' => $signature->path,
                    'fingerprint_template' => $isSimulation ? null : ($input['fingerprint_template'] ?? null),
                    'fingerprints' => $fingerprints,
                    'biometrics_captured_by' => $officer->id,
                    'biometrics_captured_at' => now(),
                    'card_id' => $card->id,
                ]);

                if ($isSimulation) {
                    Audit::log('BIOMETRICS_SIMULATION', "Development Biometrics Simulation captured for application {$locked->application_number} (photo + signature captured, fingerprints recorded as 'Not Captured — Development Simulation')", $card, actor: $officer);
                }

                Audit::log('CARD_CREATED', "Card {$card->card_number} created from application {$locked->application_number}", $card, actor: $officer);

                // A replacement for a lost or stolen card: the old card is
                // withdrawn for good once the new one exists.
                if ($locked->type === 'REPLACE' && $locked->renewal_of_card_id) {
                    $old = ResidenceCard::whereKey($locked->renewal_of_card_id)->lockForUpdate()->first();
                    if ($old && $old->status !== CardStatus::Revoked) {
                        $old->forceFill([
                            'status' => CardStatus::Revoked,
                            'revocation_reason' => "REPLACED BY {$card->card_number} ({$old->lost_report_type} CARD)",
                            'revoked_by' => $officer->id,
                            'revoked_at' => now(),
                        ])->save();
                        Audit::log('CARD_REPLACED', "Card {$old->card_number} replaced by {$card->card_number}", $old, actor: $officer);
                    }
                }
            },
        );
    }

    /**
     * Final approval of a produced card (legacy "APPROVE" on card-details).
     */
    public function approve(ResidenceCard $card, User $officer): ResidenceCard
    {
        return $this->mutate($card, [CardStatus::Approved], function (ResidenceCard $c) use ($officer) {
            $c->status = CardStatus::Issued;
            $c->approved_by = $officer->id;
            $c->approved_at = now();
            $c->query_reason = null;
        }, 'CARD_APPROVED', 'Card approved and issued', $officer);
    }

    public function query(ResidenceCard $card, User $officer, string $reason): ResidenceCard
    {
        return $this->mutate($card, [CardStatus::Approved], function (ResidenceCard $c) use ($reason) {
            $c->status = CardStatus::Queried;
            $c->query_reason = $reason;
        }, 'CARD_QUERIED', "Card queried: {$reason}", $officer);
    }

    /**
     * Staff edit of card particulars. A queried card returns to APPROVED
     * (awaiting final approval) once corrected.
     */
    public function update(ResidenceCard $card, User $officer, array $particulars): ResidenceCard
    {
        return $this->mutate($card, [CardStatus::Approved, CardStatus::Queried], function (ResidenceCard $c) use ($particulars) {
            $c->fill($particulars);
            $c->status = CardStatus::Approved;
        }, 'CARD_UPDATED', 'Card particulars corrected', $officer, ['fields' => array_keys($particulars)]);
    }

    /**
     * Card printed and approved -> application READY_FOR_COLLECTION.
     * (Fixes the legacy bug that also flagged every other application with
     * the same passport number.)
     */
    public function markReadyForCollection(ResidenceCard $card, User $officer): Application
    {
        if (! $card->status->isActive() && $card->status !== CardStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'Only an approved/issued card can be marked ready for collection.']);
        }

        $application = $card->application ?? throw ValidationException::withMessages(['card' => 'This card is not linked to an application.']);

        return $this->workflow->transition($application, ApplicationStatus::ReadyForCollection, $officer, 'Card ready for collection',
            fn (Application $a) => $a->ready_at = now());
    }

    public function markCollected(Application $application, User $officer, ?string $notes = null): Application
    {
        return $this->workflow->transition($application, ApplicationStatus::Issued, $officer, $notes ?? 'Card collected by holder',
            function (Application $a) use ($officer) {
                if (! $a->ready_at) {
                    $a->ready_at = now();
                }
                $a->collected_at = now();
                $a->collected_by = $officer->id;

                if ($a->card_id) {
                    $card = ResidenceCard::find($a->card_id);
                    if ($card && $card->status !== CardStatus::Revoked) {
                        $card->status = CardStatus::Issued;
                        $card->issued_on = $card->issued_on ?? today();
                        $card->issuing_officer_id = $officer->id;
                        $card->issuing_officer_name = $officer->fullname;
                        $card->issuing_officer_service_no = $officer->service_number;
                        $card->saveQuietly();
                    }
                }
            });
    }

    public function renew(ResidenceCard $card, User $officer, array $input): ResidenceCard
    {
        if ($card->is_watchlisted) {
            throw ValidationException::withMessages(['card' => 'A watchlisted card cannot be renewed.']);
        }

        return $this->mutate($card, [CardStatus::Issued, CardStatus::Renewed], function (ResidenceCard $c) use ($officer, $input) {
            $next = (int) $c->renewals()->max('renewal_number') + 1;

            CardRenewal::create([
                'card_id' => $c->id,
                'renewal_number' => $next,
                'from_date' => $input['from_date'],
                'to_date' => $input['to_date'],
                'renewed_at' => mb_strtoupper($input['renewed_at'] ?? $officer->command),
                'endorsing_officer_id' => $officer->id,
                'endorsing_officer' => mb_strtoupper($officer->fullname),
                'officer_service_no' => $officer->service_number,
                'fee_paid_kobo' => (int) round(($input['fee_paid_naira'] ?? 0) * 100),
                'receipt_number' => $input['receipt_number'] ?? null,
                'remarks' => mb_strtoupper($input['remarks'] ?? 'RENEWAL GRANTED'),
            ]);

            $c->expires_on = $input['to_date'];
            $c->expiry_reminder_days = null;
            $c->status = CardStatus::Renewed;
        }, 'CARD_RENEWED', "Card renewed until {$input['to_date']}", $officer);
    }

    public function revoke(ResidenceCard $card, User $officer, string $reason): ResidenceCard
    {
        return $this->mutate($card, [CardStatus::Issued, CardStatus::Renewed, CardStatus::Approved], function (ResidenceCard $c) use ($officer, $reason) {
            $c->status = CardStatus::Revoked;
            $c->revocation_reason = $reason;
            $c->revoked_by = $officer->id;
            $c->revoked_at = now();
        }, 'CARD_REVOKED', "Card revoked: {$reason}", $officer);
    }

    public function reinstate(ResidenceCard $card, User $officer): ResidenceCard
    {
        return $this->mutate($card, [CardStatus::Revoked], function (ResidenceCard $c) {
            $c->status = CardStatus::Issued;
            $c->revocation_reason = null;
            $c->revoked_by = null;
            $c->revoked_at = null;
        }, 'CARD_REINSTATED', 'Revoked card reinstated', $officer);
    }

    public function setWatchlist(ResidenceCard $card, User $officer, bool $watchlisted, ?string $reason): ResidenceCard
    {
        return DB::transaction(function () use ($card, $officer, $watchlisted, $reason) {
            $c = ResidenceCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $c->fill($watchlisted
                ? ['is_watchlisted' => true, 'watchlist_reason' => $reason, 'watchlisted_by' => $officer->id, 'watchlisted_at' => now()]
                : ['is_watchlisted' => false, 'watchlist_reason' => null, 'watchlisted_by' => null, 'watchlisted_at' => null]
            )->save();

            Audit::log($watchlisted ? 'CARD_WATCHLISTED' : 'CARD_WATCHLIST_CLEARED',
                $watchlisted ? "Watchlisted: {$reason}" : 'Removed from watchlist', $c, actor: $officer);

            return $c;
        });
    }

    /**
     * The holder reports the card lost or stolen online. Verification then
     * shows it as invalid straight away; a replacement can be applied for.
     */
    public function reportLost(ResidenceCard $card, Applicant $holder, string $type, string $details, ?string $policeReport = null, ?string $incidentDate = null, ?string $incidentLocation = null, ?string $supportingDocPath = null): ResidenceCard
    {
        return DB::transaction(function () use ($card, $holder, $type, $details, $policeReport, $incidentDate, $incidentLocation, $supportingDocPath) {
            $c = ResidenceCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($c->applicant_id !== $holder->id) {
                abort(404);
            }
            if (! $c->status->isActive() && $c->status !== CardStatus::Approved) {
                throw ValidationException::withMessages(['card' => "Card {$c->card_number} is {$c->status->value} and cannot be reported."]);
            }
            if ($c->reported_lost_at) {
                throw ValidationException::withMessages(['card' => 'This card has already been reported '.strtolower((string) $c->lost_report_type).'.']);
            }

            $c->forceFill([
                'reported_lost_at' => now(),
                'lost_report_type' => $type,
                'lost_report_details' => $details,
                'police_report_number' => $policeReport,
            ])->save();

            $reportRef = $this->numbers->lostCardReportReference();
            $report = \App\Models\LostCardReport::create([
                'report_reference' => $reportRef,
                'applicant_id' => $holder->id,
                'card_id' => $c->id,
                'application_id' => $c->application?->id,
                'report_type' => $type,
                'incident_date' => $incidentDate ?: today(),
                'incident_location' => $incidentLocation,
                'details' => $details,
                'police_report_number' => $policeReport,
                'supporting_document_path' => $supportingDocPath,
                'status' => 'SUBMITTED',
                'action_history' => [
                    [
                        'action' => 'SUBMITTED',
                        'by' => $holder->fullName(),
                        'at' => now()->toIso8601String(),
                        'notes' => "Lost card report submitted for Card {$c->card_number}",
                    ],
                ],
            ]);

            Audit::log('CARD_REPORTED_'.$type, "Card {$c->card_number} reported {$type} by the holder (Ref: {$reportRef})", $c, ['report_reference' => $reportRef, 'police_report_number' => $policeReport], $holder);

            $c->setRelation('latestLostReport', $report);

            return $c;
        });
    }

    /** The card was found and handed back: the report is withdrawn. */
    public function clearLostReport(ResidenceCard $card, User $officer, string $notes): ResidenceCard
    {
        return DB::transaction(function () use ($card, $officer, $notes) {
            $c = ResidenceCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            if (! $c->reported_lost_at) {
                throw ValidationException::withMessages(['card' => 'This card has not been reported lost or stolen.']);
            }
            if ($c->status === CardStatus::Revoked) {
                throw ValidationException::withMessages(['card' => 'This card has already been replaced or revoked.']);
            }
            $type = $c->lost_report_type;
            $c->forceFill(['reported_lost_at' => null, 'lost_report_type' => null, 'lost_report_details' => null, 'police_report_number' => null])->save();

            $openReport = \App\Models\LostCardReport::where('card_id', $c->id)
                ->whereIn('status', ['SUBMITTED', 'UNDER_REVIEW', 'ADDITIONAL_INFO_REQUIRED'])
                ->latest('id')
                ->first();
            if ($openReport) {
                $history = $openReport->action_history ?: [];
                $history[] = [
                    'action' => 'RESOLVED',
                    'by' => $officer->fullname,
                    'at' => now()->toIso8601String(),
                    'notes' => "Report withdrawn by officer: {$notes}",
                ];
                $openReport->forceFill([
                    'status' => 'RESOLVED',
                    'admin_notes' => $notes,
                    'action_history' => $history,
                    'resolved_at' => now(),
                    'resolved_by' => $officer->id,
                ])->save();
            }

            Audit::log('CARD_LOST_REPORT_CLEARED', "Card {$c->card_number}: {$type} report withdrawn — {$notes}", $c, actor: $officer);

            return $c;
        });
    }

    /**
     * @param  list<CardStatus>  $allowedFrom
     */
    private function mutate(ResidenceCard $card, array $allowedFrom, callable $change, string $action, string $description, User $officer, array $context = []): ResidenceCard
    {
        return DB::transaction(function () use ($card, $allowedFrom, $change, $action, $description, $officer, $context) {
            $c = ResidenceCard::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! in_array($c->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => "Card {$c->card_number} is {$c->status->value}; this action is not allowed."]);
            }

            $change($c);
            $c->save();
            Audit::log($action, "Card {$c->card_number}: {$description}", $c, $context, $officer);

            return $c;
        });
    }
}
