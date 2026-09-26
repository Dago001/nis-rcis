<?php

namespace App\Notifications;

use App\Models\Applicant;
use App\Models\ResidenceCard;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mailed 90, 30 and 7 days before a residence card expires. */
class CardExpiryReminder extends Notification implements ShouldQueue
{
    use PushesToDevices, Queueable;

    public function __construct(public ResidenceCard $card, public int $days) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, $notifiable instanceof Applicant ? ['mail', 'database'] : ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->card;

        return (new MailMessage)->subject("Your residence card expires in {$this->days} days")
            ->greeting("Dear {$c->forenames} {$c->surname},")
            ->line("Residence card {$c->card_number} expires on ".$c->expires_on?->format('d F Y').'.')
            ->line('Apply to renew it in good time so that you remain lawfully resident in Nigeria.')
            ->action('Renew my card', config('nis.frontend_url').'/portal/apply?type=RENEWAL');
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => "Card expires in {$this->days} days", 'card_number' => $this->card->card_number];
    }
}
