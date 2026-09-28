<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\ErrorEvent;
use App\Services\SystemHealth;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System health and tracked server errors (Super Administrators).
 */
class SystemController
{
    public function health(SystemHealth $health): JsonResponse
    {
        $checks = $health->checks();

        return response()->json([
            'status' => $health->overall($checks),
            'checks' => $checks,
            'environment' => config('nis.environment_label') ?: config('app.env'),
            'alert_emails' => count(config('nis.alerts.emails')),
            'errors' => ErrorEvent::orderByRaw('resolved_at is not null')->latest('last_seen_at')->limit(100)->get()->map(fn (ErrorEvent $e) => [
                'id' => $e->id, 'exception' => $e->exception, 'message' => $e->message, 'where' => "{$e->file}:{$e->line}",
                'request' => trim("{$e->method} {$e->path}"), 'occurrences' => $e->occurrences,
                'first_seen_at' => $e->first_seen_at?->toIso8601String(), 'last_seen_at' => $e->last_seen_at?->toIso8601String(),
                'resolved_at' => $e->resolved_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $event = ErrorEvent::findOrFail($id);
        $event->update(['resolved_at' => now()]);
        Audit::log('ERROR_RESOLVED', "Server error marked resolved: {$event->exception} at {$event->file}:{$event->line}", $event);

        return response()->json(['resolved_at' => $event->resolved_at]);
    }
}
