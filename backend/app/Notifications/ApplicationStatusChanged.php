<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant about every step of their application (the legacy
 * system only notified the officer who made the decision).
 */
class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public ApplicationStatus $status;

    public function __construct(public Application $application, public ?string $notes = null)
    {
        $this->status = $application->status;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = $this->application;
        $url = config('nis.frontend_url')."/portal/applications/{$app->id}";

        $mail = (new MailMessage)
            ->subject("Residence card application {$app->application_number}: {$this->status->label()}")
            ->greeting("Dear {$app->forenames} {$app->surname},");

        return match ($this->status) {
            ApplicationStatus::PendingApproval => $mail
                ->line("Your application {$app->application_number} has been received and is awaiting review by an Approving Officer.")
                ->line("Reference: {$app->reference_number}")
                ->action('View application', $url),
            ApplicationStatus::ApprovedForBiometrics => $mail
                ->line('Your application has been APPROVED for biometrics capture.')
                ->line("Appointment: {$app->appointment_date?->format('l, j F Y')} at {$app->appointment_time}, {$app->enrollmentCenter?->name}.")
                ->line('Bring your original passport and print your appointment slip from the portal.')
                ->action('Print appointment slip', $url),
            ApplicationStatus::Queried => $mail
                ->line('An officer has QUERIED your application and needs you to take action:')
                ->line($this->notes ?? 'Please review your application in the portal.')
                ->action('Respond to query', $url),
            ApplicationStatus::Rejected => $mail
                ->line('We regret to inform you that your application has been REJECTED.')
                ->line('Reason: '.($this->notes ?? 'Not stated.')),
            ApplicationStatus::BiometricsCaptured => $mail
                ->line('Your biometrics have been captured. Your residence card is now in production.'),
            ApplicationStatus::ReadyForCollection => $mail
                ->line("Your residence card is READY FOR COLLECTION at {$app->enrollmentCenter?->name}.")
                ->line('Bring your original passport and your application slip.')
                ->action('View collection details', $url),
            ApplicationStatus::Issued => $mail
                ->line('Your residence card has been collected. Thank you.'),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'status' => $this->status->value,
            'title' => $this->status->label(),
            'notes' => $this->notes,
        ];
    }
}
