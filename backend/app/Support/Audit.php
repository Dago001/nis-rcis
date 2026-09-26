<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Append-only audit trail writer.
 */
class Audit
{
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $context = [],
        ?Model $actor = null,
        ?string $actorLabel = null,
    ): void {
        $actor ??= Auth::user();
        $request = request();

        try {
            $log = new AuditLog([
                'actor_label' => $actorLabel
                    ?? ($actor && method_exists($actor, 'auditLabel') ? $actor->auditLabel() : 'SYSTEM'),
                'action' => $action,
                'description' => $description,
                'context' => $context ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            ]);

            if ($actor) {
                $log->actor()->associate($actor);
            }
            if ($subject) {
                $log->subject()->associate($subject);
            }

            $log->save();
        } catch (Throwable $e) {
            // Auditing must never break the request, but failures are loud.
            Log::error('Audit log write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
