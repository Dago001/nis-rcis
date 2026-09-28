<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The verification link is ONLY delivered by e-mail (the legacy portal
 * printed it on screen, which made verification meaningless).
 */
class VerifyApplicantEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('applicant.verify', now()->addHours(24), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject('Verify your NIS Residence Card Portal account')
            ->greeting("Dear {$notifiable->forenames},")
            ->line('Please confirm your e-mail address to activate your applicant account.')
            ->action('Verify e-mail address', $url)
            ->line('This link expires in 24 hours. If you did not create an account, ignore this e-mail.');
    }
}
