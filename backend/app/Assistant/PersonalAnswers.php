<?php

namespace App\Assistant;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;

/**
 * Answers about the signed-in applicant's OWN application.
 *
 * These answers are built from fixed templates here, never by the language
 * model: personal data is not sent to any third party, and the only record
 * that can be read is the latest application belonging to the applicant who
 * owns the OAuth access token. There is no way to ask about someone else.
 */
class PersonalAnswers
{
    private const INTENTS = [
        'appointment' => '/\b(appointment|biometric|biometrics|capture date|when (do|should|must) i (go|come|attend))\b/i',
        'query' => '/\b(queried|query|why .*(query|reject)|officer.{0,20}(say|said|want|note)|what .*(correct|fix))\b/i',
        'collection' => '/\b(ready|collect|collection|pick up|pickup)\b/i',
        'card' => '/\bmy (residence )?card\b.{0,40}\b(expire|expiry|valid|number|status)\b|\b(when|does) my card expire\b/i',
        'status' => '/\b(status|progress|stage|update|approved|where is|what.?s happening|how far)\b/i',
    ];

    /** Does the message ask about the user's own application? */
    public function isPersonal(string $message): bool
    {
        $aboutMine = preg_match('/\bmy\s+(own\s+)?(application|status|appointment|card|residence card|query|submission|biometrics?\s+(appointment|date|booking))\b|\b(am i|have i been|was i|is mine)\b/i', $message);

        return (bool) $aboutMine && $this->intent($message) !== null;
    }

    public function intent(string $message): ?string
    {
        foreach (self::INTENTS as $intent => $pattern) {
            if (preg_match($pattern, $message)) {
                return $intent;
            }
        }

        return null;
    }

    public function forGuest(): Reply
    {
        return new Reply(
            'To see your own application, please sign in to the applicant portal. You can also check its status on the Track page with your application number and passport number.',
            'personal',
            [['label' => 'Sign in', 'href' => '/login'], ['label' => 'Track an application', 'href' => '/track']],
        );
    }

    public function forApplicant(Applicant $applicant, string $message): Reply
    {
        /** @var Application|null $application */
        $application = $applicant->applications()->with(['enrollmentCenter', 'card'])->latest('id')->first();

        if (! $application) {
            return new Reply(
                'You have not submitted an application yet. Click "New application" in the portal to start; you can save and continue at any time.',
                'personal',
                [['label' => 'Start an application', 'href' => '/portal/apply']],
            );
        }

        $number = $application->application_number;
        $link = [['label' => 'Open my application', 'href' => '/portal/applications/'.$application->id]];
        $status = $application->status;

        $text = match ($this->intent($message)) {
            'appointment' => $this->appointment($application),
            'query' => match ($status) {
                ApplicationStatus::Queried => "Your application {$number} was queried. The officer's note: \"{$this->note($application)}\" Open the application, upload the corrected document and send your response.",
                ApplicationStatus::Rejected => "Your application {$number} was rejected. The reason given: \"{$this->note($application)}\"",
                default => "Your application {$number} has no open query. Its status is: {$status->label()}.",
            },
            'collection' => match ($status) {
                ApplicationStatus::ReadyForCollection => "Good news: your card is ready for collection at {$this->center($application)}. Bring your original passport.",
                ApplicationStatus::Issued => 'You have already collected your card.',
                default => "Your card is not ready yet. Your application {$number} is at the stage: {$status->label()}. You will get an e-mail when it is ready.",
            },
            'card' => $this->card($application),
            default => $this->status($application),
        };

        return new Reply($text, 'personal', $link);
    }

    private function status(Application $application): string
    {
        $number = $application->application_number;

        return match ($application->status) {
            ApplicationStatus::PendingApproval => "Your application {$number} is pending approval. An Approving Officer will review it; you will be notified by e-mail.",
            ApplicationStatus::Queried => "Your application {$number} was queried and needs your action. The officer's note: \"{$this->note($application)}\"",
            ApplicationStatus::Rejected => "Your application {$number} was rejected. The reason given: \"{$this->note($application)}\"",
            ApplicationStatus::ApprovedForBiometrics => "Your application {$number} is approved for biometrics. {$this->appointment($application)}",
            ApplicationStatus::BiometricsCaptured => "Your biometrics for application {$number} have been captured and your card is being produced. You will be notified when it is ready.",
            ApplicationStatus::ReadyForCollection => "Your card is ready for collection at {$this->center($application)}. Bring your original passport.",
            ApplicationStatus::Issued => "Your application {$number} is complete and your card has been collected.",
        };
    }

    private function appointment(Application $application): string
    {
        if (in_array($application->status, [ApplicationStatus::BiometricsCaptured, ApplicationStatus::ReadyForCollection, ApplicationStatus::Issued], true)) {
            return 'Your biometrics have already been captured.';
        }

        return sprintf(
            'Your biometrics appointment is on %s at %s, at %s. Bring your original passport and your appointment slip.',
            $application->appointment_date?->format('l, j F Y') ?? 'a date to be confirmed',
            $application->appointment_time,
            $this->center($application),
        );
    }

    private function card(Application $application): string
    {
        $card = $application->card;
        if (! $card) {
            return "No card has been produced for application {$application->application_number} yet. Status: {$application->status->label()}.";
        }

        $expiry = $card->expires_on ? ' It is valid until '.$card->expires_on->format('j F Y').'.' : '';

        return "Your residence card status is {$card->status->value}.{$expiry}";
    }

    private function center(Application $application): string
    {
        return $application->enrollmentCenter?->name ?? 'your enrollment center';
    }

    private function note(Application $application): string
    {
        return str_replace('"', "'", (string) ($application->decision_notes ?: 'see the portal for details'));
    }
}
