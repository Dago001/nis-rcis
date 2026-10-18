<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Services\PassportDataPurger;
use Illuminate\Console\Command;

class ResolveApplication extends Command
{
    protected $signature = 'nis:resolve-application
        {application_number : The application number (e.g. RC-2026-100014)}
        {--status= : Transition to a new status (e.g. ISSUED, REJECTED, PENDING_APPROVAL)}
        {--purge : Completely purge this application, passport, and all related records from the database}
        {--notes= : Optional notes for the transition}';

    protected $description = 'Inspect or resolve an application status in the database';

    public function handle(): int
    {
        $appNumber = $this->argument('application_number');
        $application = Application::with(['applicant', 'card'])->where('application_number', $appNumber)->first();

        if (! $application) {
            $this->error("Application {$appNumber} was not found.");
            return self::FAILURE;
        }

        $this->info("=== Application {$application->application_number} ===");
        $this->line("ID: {$application->id}");
        $this->line("Current Status: {$application->status->value} ({$application->status->label()})");
        $this->line("Type: {$application->type}");
        $this->line("Applicant ID: {$application->applicant_id} (" . ($application->applicant?->email ?? 'N/A') . ")");
        $this->line("Passport Number: {$application->passport_number}");
        $this->line("Card ID: " . ($application->card_id ?? 'None'));
        if ($application->card) {
            $this->line("Card Number: {$application->card->card_number} (Status: {$application->card->status->value})");
        }

        if ($this->option('purge')) {
            $this->warn("Completely purging application {$application->application_number} and passport {$application->passport_number}...");
            $summary = PassportDataPurger::purge($application->passport_number, $application->application_number);
            $this->info("Purged {$summary['applications_deleted']} application(s), {$summary['cards_deleted']} card(s), {$summary['documents_deleted']} document(s).");
            return self::SUCCESS;
        }

        $newStatusInput = $this->option('status');
        if (! $newStatusInput) {
            $this->line("");
            $this->line("Tip: To resolve or complete this application, run:");
            $this->line("  php artisan nis:resolve-application {$appNumber} --status=ISSUED");
            return self::SUCCESS;
        }

        $targetStatus = ApplicationStatus::tryFrom(strtoupper(trim($newStatusInput)));
        if (! $targetStatus) {
            $this->error("Invalid status '{$newStatusInput}'. Valid statuses: " . implode(', ', array_map(fn ($s) => $s->value, ApplicationStatus::cases())));
            return self::FAILURE;
        }

        $application->status = $targetStatus;
        if ($targetStatus === ApplicationStatus::Issued && ! $application->collected_at) {
            $application->collected_at = now();
        }
        if ($this->option('notes')) {
            $application->decision_notes = $this->option('notes');
        }
        $application->save();

        $this->info("Successfully updated {$application->application_number} to status: {$targetStatus->value}");

        return self::SUCCESS;
    }
}
