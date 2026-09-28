<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\IntegrationCheck;
use App\Models\ResidenceCard;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fraud and duplicate checks run on every submitted application.
 *
 * Flags are advisory: they are shown to officers in the approval queue and
 * never block or change the workflow on their own.
 */
class RiskChecker
{
    public const HIGH = 'HIGH';

    public const MEDIUM = 'MEDIUM';

    /**
     * @return list<array{code: string, severity: string, message: string, related: list<string>}>
     */
    public function check(Application $application): array
    {
        $flags = [];
        $others = fn (): Builder => Application::query()->whereKeyNot($application->id);
        $notSamePerson = function (Builder $q) use ($application) {
            $application->applicant_id
                ? $q->where(fn ($w) => $w->whereNull('applicant_id')->orWhere('applicant_id', '!=', $application->applicant_id))
                : $q;
        };

        // 1. The same passport under another account or with different particulars.
        $samePassport = $others()->where('passport_number', $application->passport_number)->get(['id', 'application_number', 'applicant_id', 'surname', 'date_of_birth']);
        $otherAccounts = $samePassport->filter(fn ($a) => $application->applicant_id && $a->applicant_id && $a->applicant_id !== $application->applicant_id);
        if ($otherAccounts->isNotEmpty()) {
            $flags[] = $this->flag('PASSPORT_OTHER_ACCOUNT', self::HIGH, 'The same passport number was used by a different applicant account.', $otherAccounts->pluck('application_number'));
        }
        $mismatch = $samePassport->filter(fn ($a) => mb_strtoupper($a->surname) !== mb_strtoupper($application->surname)
            || $a->date_of_birth?->toDateString() !== $application->date_of_birth?->toDateString());
        if ($mismatch->isNotEmpty()) {
            $flags[] = $this->flag('PASSPORT_PARTICULARS_MISMATCH', self::HIGH, 'Another application with this passport number has a different surname or date of birth.', $mismatch->pluck('application_number'));
        }

        // 2. A card on this passport is revoked or watchlisted.
        $cards = ResidenceCard::query()->where('passport_number', $application->passport_number)
            ->where(fn ($q) => $q->where('status', CardStatus::Revoked)->orWhere('is_watchlisted', true))->pluck('card_number');
        if ($cards->isNotEmpty()) {
            $flags[] = $this->flag('PASSPORT_CARD_REVOKED_OR_WATCHLISTED', self::HIGH, 'A residence card on this passport is revoked or watchlisted.', $cards);
        }

        // 3. The same phone number on other applicants' applications.
        if ($application->phone) {
            $samePhone = $others()->where('phone', $application->phone)->tap($notSamePerson)->limit(10)->pluck('application_number');
            if ($samePhone->isNotEmpty()) {
                $flags[] = $this->flag('PHONE_SHARED', self::MEDIUM, 'The same phone number is used on other applicants\' applications.', $samePhone);
            }
        }

        // 4. The same photograph file on another person's application.
        $photoHashes = ApplicationDocument::query()->where('application_id', $application->id)
            ->where('type', DocumentType::Photo)->pluck('sha256');
        if ($photoHashes->isNotEmpty()) {
            $samePhoto = Application::query()->whereKeyNot($application->id)->tap($notSamePerson)
                ->whereIn('id', ApplicationDocument::query()->where('type', DocumentType::Photo)->whereIn('sha256', $photoHashes)->whereNotNull('application_id')->select('application_id'))
                ->where('passport_number', '!=', $application->passport_number)
                ->limit(10)->pluck('application_number');
            if ($samePhoto->isNotEmpty()) {
                $flags[] = $this->flag('PHOTO_DUPLICATE', self::HIGH, 'The identical photograph was submitted on another person\'s application.', $samePhoto);
            }
        }

        // 5. Many applications from one account in a short time.
        if ($application->applicant_id) {
            $recent = Application::where('applicant_id', $application->applicant_id)->where('created_at', '>=', now()->subDays(30))->count();
            if ($recent > 5) {
                $flags[] = $this->flag('HIGH_VOLUME_ACCOUNT', self::MEDIUM, "This account submitted {$recent} applications in the last 30 days.", collect());
            }
        }

        // 6. Answers from other government systems (Interpol SLTD, Ministry of Interior).
        foreach (IntegrationChecks::latest($application) as $check) {
            match ([$check->service, $check->status]) {
                [IntegrationCheck::INTERPOL_SLTD, 'HIT'] => $flags[] = $this->flag('INTERPOL_SLTD_HIT', self::HIGH, 'Interpol SLTD: the passport is recorded as stolen or lost. Hold the applicant and the passport; contact NCB Abuja.', [$check->reference]),
                [IntegrationCheck::MOI_QUOTA, 'NOT_FOUND'], [IntegrationCheck::MOI_QUOTA, 'INVALID'] => $flags[] = $this->flag('QUOTA_NOT_CONFIRMED', self::MEDIUM, $check->summary, [$check->reference]),
                default => null,
            };
        }

        $application->forceFill(['risk_flags' => $flags ?: null, 'risk_checked_at' => now()])->saveQuietly();

        return $flags;
    }

    private function flag(string $code, string $severity, string $message, iterable $related): array
    {
        return ['code' => $code, 'severity' => $severity, 'message' => $message, 'related' => array_values(collect($related)->unique()->all())];
    }
}
