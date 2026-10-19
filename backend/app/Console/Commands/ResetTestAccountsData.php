<?php

namespace App\Console\Commands;

use App\Services\TestAccountResetter;
use Illuminate\Console\Command;

class ResetTestAccountsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:reset-accounts {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge test applications, drafts, cards, and associated records for blessingeze00034@gmail.com and ezeab.nis@gmail.com';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Resetting test data for test accounts...');

        $summary = TestAccountResetter::reset();

        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($val, $key) => [
                'Metric' => $key,
                'Value' => is_array($val) ? implode(', ', $val) : (string) $val,
            ])->values()->all()
        );

        $this->info('Test accounts successfully reset while preserving applicant logins.');

        return Command::SUCCESS;
    }
}
