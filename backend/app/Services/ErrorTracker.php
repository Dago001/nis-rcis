<?php

namespace App\Services;

use App\Models\ErrorEvent;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Self-hosted error tracking: every reported server error is grouped by
 * where it happened, counted, and e-mailed to the alert addresses the first
 * time it appears (or reappears after being resolved), at most hourly.
 * Messages are cleaned of e-mail addresses and long numbers first.
 */
class ErrorTracker
{
    private static bool $capturing = false;

    public function capture(Throwable $e): void
    {
        // Never recurse (e.g. when the database itself is failing).
        if (self::$capturing) {
            return;
        }
        self::$capturing = true;

        try {
            $file = Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR);
            $fingerprint = sha1($e::class.'|'.$file.'|'.$e->getLine());
            $request = app()->runningInConsole() ? null : request();
            $message = $this->scrub($e->getMessage());

            $event = ErrorEvent::firstOrNew(['fingerprint' => $fingerprint]);
            $regressed = $event->exists && $event->resolved_at !== null;
            $event->fill([
                'exception' => Str::limit($e::class, 200, ''),
                'message' => Str::limit($message, 2000),
                'file' => Str::limit($file, 300, ''),
                'line' => $e->getLine(),
                'method' => $request?->method(),
                'path' => $request ? Str::limit('/'.ltrim($request->path(), '/'), 300, '') : 'console',
                'occurrences' => $event->exists ? $event->occurrences + 1 : 1,
                'first_seen_at' => $event->first_seen_at ?? now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]);

            $alert = (! $event->exists || $regressed) && (! $event->alerted_at || $event->alerted_at->lt(now()->subHour()));
            if ($alert) {
                $event->alerted_at = now();
            }
            $event->save();

            if ($alert && $emails = config('nis.alerts.emails')) {
                Notification::route('mail', $emails)->notify(new SystemAlert(
                    ($regressed ? 'Server error is back: ' : 'New server error: ').class_basename($e),
                    ["{$event->exception}: {$event->message}", "Where: {$event->file}:{$event->line}", "Request: {$event->method} {$event->path}"],
                ));
            }
        } catch (Throwable) {
            // Tracking must never break the request; the error is still in the log.
        } finally {
            self::$capturing = false;
        }
    }

    private function scrub(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? $message;

        return preg_replace('/\b\d{6,}\b/', '[number]', $message) ?? $message;
    }
}
