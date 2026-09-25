<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\AuditLog;
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
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('actor_label', 'ilike', "%{$s}%")->orWhere('description', 'ilike', "%{$s}%")))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(50);

        return response()->json($logs);
    }
}
