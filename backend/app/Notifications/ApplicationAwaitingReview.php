<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationAwaitingReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'title' => 'Application awaiting review',
            'notes' => "{$this->application->surname}, {$this->application->forenames} — {$this->application->nationality}",
        ];
    }
}
