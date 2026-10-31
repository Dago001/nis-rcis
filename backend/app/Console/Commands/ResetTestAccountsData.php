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
    protected $signature = 'test:reset-accounts {--inspect : Display inventory of records without deleting} {--dry-run : Alias for --inspect} {--target= : Specific email address to target} {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect or purge test applications, drafts, cards, and associated records while preserving applicant login credentials.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetOption = $this->option('target');
        $targets = $targetOption ? array_map('trim', explode(',', $targetOption)) : TestAccountResetter::TARGET_EMAILS;

        if ($this->option('inspect') || $this->option('dry-run')) {
            $this->info('Running pre-deletion inventory inspect...');
            $scope = TestAccountResetter::inspect($targets);

            $this->line('');
            $this->warn('PRE-DELETION INVENTORY SCOPE (DRY RUN — NO DATA MODIFIED)');
            $this->line('Environment: '.($scope['environment'] ?? 'unknown'));
            $this->line('Target Emails: '.implode(', ', $scope['target_emails']));
            $this->line('');

            $this->info('Preserved Applicant Account(s):');
            $this->table(
                ['ID', 'Email', 'Surname', 'Forenames', 'Account Status'],
                $scope['applicants']
            );

            $this->line('');
            $this->info('Associated Records Identified for Deletion:');
            $this->table(
                ['Record Type / Table', 'Record Count'],
                collect($scope['counts'])->map(fn ($count, $table) => [
                    'table' => $table,
                    'count' => $count,
                ])->values()->all()
            );

            if (! empty($scope['applications'])) {
                $this->line('');
                $this->info('Applications Identified:');
                $this->table(
                    ['ID', 'App Number', 'Type', 'Status', 'Category', 'Submitted At'],
                    $scope['applications']
                );
            }

            if (! empty($scope['cards'])) {
                $this->line('');
                $this->info('Residence Cards Identified:');
                $this->table(
                    ['ID', 'Card Number', 'Status'],
                    $scope['cards']
                );
            }

            return Command::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to delete all application data for '.implode(', ', $targets).'? This action is irreversible.')) {
                $this->warn('Reset aborted by user.');
                return Command::FAILURE;
            }
        }

        $this->info('Resetting test data for test accounts...');

        $summary = TestAccountResetter::reset($targets);

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
