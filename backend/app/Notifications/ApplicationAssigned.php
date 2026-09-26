<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public User $by)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Application {$this->application->application_number} assigned to you")
            ->greeting("Dear {$notifiable->fullname},")
            ->line("{$this->by->fullname} has assigned application {$this->application->application_number} ({$this->application->surname}, {$this->application->forenames}) to you.")
            ->action('Open the application', config('nis.frontend_url')."/staff/applications/{$this->application->id}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'title' => 'Application assigned to you',
            'notes' => "By {$this->by->fullname}",
        ];
    }
}
