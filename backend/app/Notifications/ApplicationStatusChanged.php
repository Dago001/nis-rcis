<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant about every step of their application (the legacy
 * system only notified the officer who made the decision).
 */
class ApplicationStatusChanged extends Notification
{
    use PushesToDevices, Queueable;

    public ApplicationStatus $status;

    public function __construct(public Application $application, public ?string $notes = null)
    {
        $this->status = $application->status;
    }

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = $this->application;
        $isQueried = $this->status === ApplicationStatus::Queried;
        $url = $isQueried
            ? config('nis.frontend_url')."/portal/apply?query_app={$app->id}&step=4"
            : config('nis.frontend_url')."/portal/applications/{$app->id}";

        $mail = (new MailMessage)
            ->subject("ECOWAS residence card application {$app->application_number}: {$this->status->label()}")
            ->greeting("Dear {$app->forenames} {$app->surname},");

        return match ($this->status) {
            ApplicationStatus::PendingApproval => $mail
                ->line("Your application {$app->application_number} has been received and is awaiting vetting by a Vetting Officer.")
                ->line("Reference: {$app->reference_number}")
                ->action('View application', $url),
            ApplicationStatus::Vetted => $mail
                ->line("Your application {$app->application_number} has passed vetting and is awaiting final approval from an Approving Officer.")
                ->line("Please note: clearance to attend your scheduled biometrics appointment is subject to final approval.")
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
                ->line('Your ECOWAS Residence Card has been issued and collected. Thank you.')
                ->action('View application details', $url),
        };
    }

    public function toArray(object $notifiable): array
    {
        $isQueried = $this->status === ApplicationStatus::Queried;

        $title = match ($this->status) {
            ApplicationStatus::Vetted => 'Application Vetted – Awaiting Approval',
            ApplicationStatus::Queried => 'Application Requires Your Attention',
            ApplicationStatus::ApprovedForBiometrics => 'Application Approved for Biometrics',
            ApplicationStatus::ReadyForCollection => 'Card Ready for Collection',
            ApplicationStatus::Issued => 'Card Issued & Collected',
            default => $this->status->label(),
        };

        $notes = match ($this->status) {
            ApplicationStatus::Vetted => $this->notes ?? 'Your application has passed vetting and is awaiting final approval from an Approving Officer.',
            ApplicationStatus::ReadyForCollection => $this->notes ?? "Your residence card is ready for collection at {$this->application->enrollmentCenter?->name}. Bring your original passport and application slip.",
            ApplicationStatus::Issued => $this->notes ?? 'Your residence card has been successfully issued and collected. Thank you.',
            default => $this->notes,
        };

        $actionUrl = $isQueried
            ? "/portal/apply?query_app={$this->application->id}&step=4"
            : "/portal/applications/{$this->application->id}";

        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'reference_number' => $this->application->reference_number,
            'status' => $this->status->value,
            'title' => $title,
            'notes' => $notes,
            'query_reason' => $this->notes,
            'action_url' => $actionUrl,
            'action_label' => $isQueried ? 'Respond to Query' : ($this->status === ApplicationStatus::ReadyForCollection ? 'View Collection Details' : 'View Application'),
        ];
    }
}
