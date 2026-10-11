<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffTemporaryPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $password,
        public string $actionType = 'created' // 'created' or 'reset'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('nis.frontend_url') . '/login/staff';

        $mail = (new MailMessage)
            ->subject('Your Nigeria Immigration Service RCIS Staff Account Credentials')
            ->greeting("Dear {$notifiable->fullname},");

        if ($this->actionType === 'created') {
            $mail->line("A new staff account has been created for you on the Nigeria Immigration Service Residence Card Information System (RCIS).")
                 ->line("**Role:** {$notifiable->role?->label()}")
                 ->line("**Service Number:** {$notifiable->service_number}")
                 ->line("**Username:** {$notifiable->username}")
                 ->line("**Command:** {$notifiable->command}");
        } else {
            $mail->line("Your password for the Nigeria Immigration Service Residence Card Information System (RCIS) staff account has been reset.");
        }

        return $mail
            ->line("**Temporary Password:** `{$this->password}`")
            ->action('Sign In to Staff Console', $loginUrl)
            ->line('For security reasons, you will be required to change this temporary password immediately upon your first sign-in.')
            ->line('Please do not share this password with anyone.');
    }
}
