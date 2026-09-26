<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\WebPushChannel;

/**
 * Adds a push notification to the applicant's installed portal, next to the
 * e-mail, when they have turned notifications on.
 */
trait PushesToDevices
{
    /** @param  list<string>  $channels */
    protected function withPush(object $notifiable, array $channels): array
    {
        return WebPushChannel::enabled($notifiable) ? [...$channels, WebPushChannel::class] : $channels;
    }

    /** @return array{title: string, body: string, url: string, tag: string} */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $path = isset($data['application_id']) ? "/portal/applications/{$data['application_id']}"
            : (isset($data['card_number']) ? '/portal/cards' : (isset($data['refund_id']) ? '/portal/payments' : '/portal'));

        return [
            'title' => (string) ($data['title'] ?? 'Nigeria Immigration Service'),
            'body' => trim(implode(' — ', array_filter([$data['application_number'] ?? $data['card_number'] ?? null, $data['notes'] ?? null]))),
            'url' => $path,
            'tag' => class_basename(static::class),
        ];
    }
}
