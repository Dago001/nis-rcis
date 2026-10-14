<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $tone = 'info',
        public ?string $url = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (WebPushChannel::enabled($notifiable)) {
            $channels[] = WebPushChannel::class;
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'broadcast',
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone,
            'url' => $this->url,
            'time' => now()->toIso8601String(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'icon' => '/images/nis-logo.png',
            'badge' => '/favicon.ico',
            'data' => [
                'url' => $this->url ?? '/portal',
            ],
        ];
    }
}
