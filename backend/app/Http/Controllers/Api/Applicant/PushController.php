<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Web push subscriptions for the installable applicant portal.
 */
class PushController
{
    public function key(): JsonResponse
    {
        return response()->json(['public_key' => config('nis.push.public_key') ?: null]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        abort_if(blank(config('nis.push.public_key')), 503, 'Push notifications are not set up on this server.');
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'content_encoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        $applicant = $request->user();
        abort_if(PushSubscription::where('applicant_id', $applicant->id)->count() >= 10, 422, 'Too many devices. Turn notifications off on an old device first.');

        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'applicant_id' => $applicant->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['subscribed' => true], 201);
    }

    public function unsubscribe(Request $request): Response
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::where('applicant_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->noContent();
    }
}
