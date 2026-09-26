<?php

namespace App\Console\Commands;

use App\Notifications\SystemAlert;
use App\Services\SystemHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled every 5 minutes: runs the health checks, records the scheduler
 * heartbeat, and e-mails the alert addresses when a check starts failing
 * and when it recovers.
 */
class HealthCheck extends Command
{
    protected $signature = 'nis:health-check';

    protected $description = 'Check the system health and e-mail alerts on failure and recovery';

    public function handle(SystemHealth $health): int
    {
        Cache::forever(SystemHealth::HEARTBEAT, now()->toIso8601String());
        $checks = $health->checks();

        $failing = collect($checks)->where('status', SystemHealth::FAIL)->pluck('detail', 'name');
        $before = collect(Cache::get('nis:health:failing', []));
        $new = $failing->diffKeys($before);
        $recovered = $before->diffKeys($failing);
        Cache::forever('nis:health:failing', $failing->all());

        $emails = config('nis.alerts.emails');
        if ($emails && $new->isNotEmpty()) {
            Notification::route('mail', $emails)->notify(new SystemAlert('Health check failing: '.$new->keys()->implode(', '),
                $new->map(fn ($detail, $name) => "{$name}: {$detail}")->values()->all()));
        }
        if ($emails && $recovered->isNotEmpty()) {
            Notification::route('mail', $emails)->notify(new SystemAlert('Recovered: '.$recovered->keys()->implode(', '),
                ['These checks are passing again.']));
        }

        foreach ($checks as $c) {
            $this->line(sprintf('%-18s %-5s %s', $c['name'], strtoupper($c['status']), $c['detail']));
        }

        return $failing->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
