<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\AuditLog;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->when($data['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('actor_label', 'ilike', Like::contains($s))->orWhere('description', 'ilike', Like::contains($s))))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(50);

        return response()->json($logs);
    }

    public function myActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $logs = AuditLog::query()
            ->where(function ($q) use ($user, $likeOp) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('actor_type', \App\Models\User::class)->where('actor_id', $user->id);
                });
                if ($user->service_number) {
                    $q->orWhere('actor_label', $likeOp, "%{$user->service_number}%");
                }
                if ($user->fullname) {
                    $q->orWhere('actor_label', $likeOp, "%{$user->fullname}%");
                }
            })
            ->when($data['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('description', $likeOp, Like::contains($s))->orWhere('action', $likeOp, Like::contains($s))))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(25);

        return response()->json($logs);
    }
}
