<?php

namespace App\Services;

use App\Models\ErrorEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Health of the running system: database, cache, document storage, disk
 * space, scheduler, backups, OAuth keys and recent server errors.
 */
class SystemHealth
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** Set by the scheduled health check; proves the scheduler is running. */
    public const HEARTBEAT = 'nis:scheduler-heartbeat';

    /** @return list<array{name: string, status: string, detail: string}> */
    public function checks(): array
    {
        return [
            $this->check('Database', function () {
                $start = microtime(true);
                DB::select('select 1');

                return [self::OK, 'Responding in '.round((microtime(true) - $start) * 1000).' ms'];
            }),
            $this->check('Cache', function () {
                $key = 'nis:health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';

                return [$ok ? self::OK : self::FAIL, $ok ? 'Read and write work ('.config('cache.default').')' : 'Could not read back a value'];
            }),
            $this->check('Document storage', function () {
                $disk = Storage::disk(config('nis.documents_disk'));
                $path = 'health/'.Str::random(12).'.txt';
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                return [$ok ? self::OK : self::FAIL, $ok ? 'Read and write work' : 'Could not read back a file'];
            }),
            $this->check('Disk space', function () {
                $free = @disk_free_space(storage_path());
                $total = @disk_total_space(storage_path());
                if (! $free || ! $total) {
                    return [self::WARN, 'Could not measure'];
                }
                $percent = round($free / $total * 100);

                // A low percentage of a very large disk is still plenty of room.
                $low = $percent < config('nis.alerts.disk_min_free_percent') && $free < config('nis.alerts.disk_min_free_gb') * (1 << 30);

                return [$low ? self::FAIL : self::OK,
                    sprintf('%s free (%d%%)', $this->bytes($free), $percent)];
            }),
            $this->check('Scheduler', function () {
                $beat = Cache::get(self::HEARTBEAT);

                return $beat && now()->diffInMinutes($beat, true) <= 15
                    ? [self::OK, 'Last run '.now()->parse($beat)->diffForHumans()]
                    : [self::WARN, 'Not seen in the last 15 minutes: reminders, retention and backups are not running'];
            }),
            $this->check('Backups', function () {
                if (blank(config('nis.backup.key'))) {
                    return [self::WARN, 'No backup key configured (BACKUP_KEY)'];
                }
                $files = glob(rtrim(config('nis.backup.path'), '/\\').DIRECTORY_SEPARATOR.'nis-rcis-*.nisbak') ?: [];
                $latest = $files ? max(array_map('filemtime', $files)) : null;
                if (! $latest) {
                    return [self::FAIL, 'No backup found'];
                }
                $hours = (int) floor((time() - $latest) / 3600);

                return [$hours > config('nis.alerts.backup_max_age_hours') ? self::FAIL : self::OK, "Latest backup {$hours} hour(s) old"];
            }),
            $this->check('Sign-in keys', fn () => is_readable(storage_path('oauth-private.key')) || filled(config('passport.private_key'))
                ? [self::OK, 'OAuth2 signing keys present']
                : [self::FAIL, 'OAuth2 signing keys missing (php artisan passport:keys)']),
            $this->check('Server errors', function () {
                $recent = ErrorEvent::whereNull('resolved_at')->where('last_seen_at', '>=', now()->subHour())->count();

                return [$recent > 0 ? self::WARN : self::OK, $recent > 0 ? "{$recent} unresolved error type(s) in the last hour" : 'None in the last hour'];
            }),
        ];
    }

    /** Overall: fail if anything failed, warn if anything warned. */
    public function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return in_array(self::FAIL, $statuses, true) ? self::FAIL : (in_array(self::WARN, $statuses, true) ? self::WARN : self::OK);
    }

    private function check(string $name, callable $probe): array
    {
        try {
            [$status, $detail] = $probe();
        } catch (Throwable $e) {
            [$status, $detail] = [self::FAIL, Str::limit(class_basename($e).': '.$e->getMessage(), 160)];
        }

        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    private function bytes(float $bytes): string
    {
        return $bytes >= 1 << 30 ? round($bytes / (1 << 30), 1).' GB' : round($bytes / (1 << 20)).' MB';
    }
}
