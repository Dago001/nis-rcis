<?php

namespace App\Notifications;

use App\Models\Applicant;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mailed the day before a biometrics appointment. */
class AppointmentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof Applicant ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->application;
        $center = $a->enrollmentCenter;

        return (new MailMessage)->subject("Reminder: biometrics appointment tomorrow ({$a->application_number})")
            ->greeting("Dear {$a->forenames} {$a->surname},")
            ->line('This is a reminder of your residence card biometrics appointment:')
            ->line('Date and time: '.$a->appointment_date?->format('l, d F Y')." at {$a->appointment_time}")
            ->line('Centre: '.($center ? "{$center->name}, {$center->address}" : 'as shown on your appointment slip'))
            ->line('Please bring your original passport, your residence visa and your printed appointment slip.')
            ->action('View my application', config('nis.frontend_url')."/portal/applications/{$a->id}");
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Biometrics appointment tomorrow', 'application_id' => $this->application->id,
            'application_number' => $this->application->application_number];
    }
}
