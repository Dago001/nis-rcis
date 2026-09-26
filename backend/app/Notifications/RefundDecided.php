<?php

namespace App\Notifications;

use App\Models\Refund;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundDecided extends Notification implements ShouldQueue
{
    use PushesToDevices, Queueable;

    public function __construct(public Refund $refund)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = '₦'.number_format($this->refund->amount_kobo / 100, 2);
        $mail = (new MailMessage)->subject("Residence card fee refund: {$this->refund->status}")
            ->greeting("Dear {$notifiable->forenames} {$notifiable->surname},");

        return match ($this->refund->status) {
            'PROCESSED' => $mail->line("Your refund of {$amount} (payment {$this->refund->payment->reference}) has been approved and sent back to your original payment method.")
                ->line('It can take 5 to 10 working days to appear, depending on your bank.'),
            'REJECTED' => $mail->line("Your request for a refund of {$amount} was not approved.")
                ->line('Reason: '.($this->refund->decision_notes ?: 'Not stated.')),
            default => $mail->line("Your refund of {$amount} was approved but Paystack could not process it yet. We will try again and contact you."),
        };
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Refund '.strtolower($this->refund->status), 'notes' => $this->refund->decision_notes, 'refund_id' => $this->refund->id];
    }
}
