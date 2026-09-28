<?php

namespace App\Notifications;

use App\Models\ResidenceCard;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardReportedLost extends Notification implements ShouldQueue
{
    use PushesToDevices, Queueable;

    public function __construct(public ResidenceCard $card)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = strtolower((string) $this->card->lost_report_type);

        return (new MailMessage)->subject("Residence card {$this->card->card_number} reported {$type}")
            ->greeting("Dear {$notifiable->forenames} {$notifiable->surname},")
            ->line("We have recorded that residence card {$this->card->card_number} was {$type} on ".now()->format('d M Y, H:i').'.')
            ->line('From now on, anyone who verifies this card is told it is not valid.')
            ->action('Apply for a replacement', config('nis.frontend_url').'/portal/cards')
            ->line('If you did not make this report, contact the Nigeria Immigration Service immediately.');
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Card reported '.strtolower((string) $this->card->lost_report_type), 'card_number' => $this->card->card_number];
    }
}
