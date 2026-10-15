<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications for the signed-in staff member or applicant.
 */
class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(50)->get()
            ->map(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'read_at' => $n->read_at, 'created_at' => $n->created_at]);

        return response()->json([
            'data' => $notifications,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($id = $request->input('id')) {
            $user->unreadNotifications()->where('id', $id)->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json(['unread' => $user->unreadNotifications()->count()]);
    }
}
