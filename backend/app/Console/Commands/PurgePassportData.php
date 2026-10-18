<?php

namespace App\Console\Commands;

use App\Services\PassportDataPurger;
use Illuminate\Console\Command;

class PurgePassportData extends Command
{
    protected $signature = 'nis:purge-passport
        {passport_number : The passport number to purge (e.g. B6556610)}
        {--application= : Optional specific application number (e.g. RC-2026-100016)}
        {--force : Force deletion without confirmation}';

    protected $description = 'Completely remove a passport number, associated applications, documents, cards, and related data from the database and storage';

    public function handle(): int
    {
        $passport = strtoupper(trim((string) $this->argument('passport_number')));
        $application = $this->option('application') ? strtoupper(trim((string) $this->option('application'))) : null;

        if (! $this->option('force')) {
            $msg = "Are you sure you want to permanently delete ALL data associated with passport '{$passport}'" .
                ($application ? " and application '{$application}'" : "") . "? This cannot be undone.";

            if (! $this->confirm($msg, false)) {
                $this->warn('Purge cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("Purging passport {$passport}" . ($application ? " and application {$application}" : "") . "...");

        $summary = PassportDataPurger::purge($passport, $application);

        $this->table(
            ['Entity / Table', 'Deleted / Cleared Count'],
            [
                ['Applications', $summary['applications_deleted']],
                ['Residence Cards', $summary['cards_deleted']],
                ['Documents & Files', $summary['documents_deleted']],
                ['Status Histories', $summary['status_histories_deleted']],
                ['Internal Notes', $summary['notes_deleted']],
                ['Integration Checks', $summary['integration_checks_deleted']],
                ['Queue Tickets', $summary['queue_tickets_deleted']],
                ['Payments', $summary['payments_deleted']],
                ['Card Renewals', $summary['card_renewals_deleted']],
                ['Card Print Jobs', $summary['card_print_jobs_deleted']],
                ['Application Drafts', $summary['drafts_deleted']],
                ['Notifications', $summary['notifications_deleted']],
                ['Applicant Profiles Cleared', $summary['applicants_updated']],
            ]
        );

        $this->info("Passport {$passport} and all related data have been completely removed from the system.");

        return self::SUCCESS;
    }
}
