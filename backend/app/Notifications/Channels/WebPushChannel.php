<?php

namespace App\Notifications\Channels;

use App\Models\Applicant;
use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Push notifications to the applicant's installed portal (web push with
 * VAPID). Sent alongside the e-mail, never instead of it. Subscriptions the
 * browser has withdrawn are removed.
 */
class WebPushChannel
{
    public static function enabled(object $notifiable): bool
    {
        return $notifiable instanceof Applicant
            && filled(config('nis.push.public_key')) && filled(config('nis.push.private_key'))
            && PushSubscription::where('applicant_id', $notifiable->id)->exists();
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::enabled($notifiable) || ! method_exists($notification, 'toWebPush')) {
            return;
        }
        $payload = json_encode($notification->toWebPush($notifiable), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $push = new WebPush(['VAPID' => [
                'subject' => config('nis.push.subject'),
                'publicKey' => config('nis.push.public_key'),
                'privateKey' => config('nis.push.private_key'),
            ]], ['TTL' => 86400]);

            foreach (PushSubscription::where('applicant_id', $notifiable->id)->get() as $sub) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]), $payload);
            }

            foreach ($push->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } elseif (! $report->isSuccess()) {
                    Log::warning('Web push failed: '.$report->getReason());
                }
            }
        } catch (Throwable $e) {
            // Push is a convenience; the e-mail has already been sent.
            Log::warning('Web push error: '.$e->getMessage());
        }
    }
}
