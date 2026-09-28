<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Models\Application;
use App\Models\ResidenceCard;
use App\Notifications\AppointmentReminder;
use App\Notifications\CardExpiryReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * E-mail reminders (e-mail only; no SMS):
 *  - biometrics appointments the day before
 *  - residence cards 90, 30 and 7 days before they expire
 */
class SendReminders extends Command
{
    protected $signature = 'nis:reminders';

    protected $description = 'E-mail appointment and card-expiry reminders';

    /** Days before expiry at which a reminder is sent, largest first. */
    public const EXPIRY_DAYS = [90, 30, 7];

    public function handle(): int
    {
        $appointments = 0;
        Application::with(['applicant', 'enrollmentCenter'])
            ->whereDate('appointment_date', now()->addDay()->toDateString())
            ->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::ApprovedForBiometrics])
            ->whereNull('appointment_reminder_sent_at')
            ->each(function (Application $a) use (&$appointments) {
                $this->send($a->applicant, $a->email, new AppointmentReminder($a));
                $a->forceFill(['appointment_reminder_sent_at' => now()])->saveQuietly();
                $appointments++;
            });

        $expiry = 0;
        ResidenceCard::with(['applicant', 'application'])
            ->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])
            ->whereNull('reported_lost_at')
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays(max(self::EXPIRY_DAYS))->toDateString()])
            ->each(function (ResidenceCard $card) use (&$expiry) {
                $left = (int) now()->startOfDay()->diffInDays($card->expires_on, false);
                $due = collect(self::EXPIRY_DAYS)->filter(fn ($d) => $left <= $d)->min();
                if ($due === null || ($card->expiry_reminder_days !== null && $card->expiry_reminder_days <= $due)) {
                    return;
                }
                $this->send($card->applicant, $card->application?->email, new CardExpiryReminder($card, $left));
                $card->forceFill(['expiry_reminder_days' => $due])->saveQuietly();
                $expiry++;
            });

        $this->info("Sent {$appointments} appointment and {$expiry} card-expiry reminder(s) by e-mail.");

        return self::SUCCESS;
    }

    private function send(?object $applicant, ?string $email, $notification): void
    {
        if ($applicant) {
            $applicant->notify($notification);
        } elseif ($email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
