<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Monitoring alert e-mailed to the system administrators (sent immediately). */
class SystemAlert extends Notification
{
    /** @param  list<string>  $lines */
    public function __construct(public string $subject, public array $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[NIS-RCIS '.(config('nis.environment_label') ?: config('app.env')).'] '.$this->subject);
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action('Open System health', config('nis.frontend_url').'/staff/system');
    }
}
