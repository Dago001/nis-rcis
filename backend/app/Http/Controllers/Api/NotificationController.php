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
        $perPage = $request->input('per_page');
        if ($perPage) {
            $paginated = $request->user()->notifications()->latest()->paginate((int) $perPage);
            $items = collect($paginated->items())->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

            return response()->json([
                'data' => $items,
                'unread' => $request->user()->unreadNotifications()->count(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ]);
        }

        $notifications = $request->user()->notifications()->latest()->limit(50)->get()
            ->map(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at?->toIso8601String()]);

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
