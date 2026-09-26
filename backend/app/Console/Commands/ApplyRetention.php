<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDraft;
use App\Models\Payment;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Data retention (Nigeria Data Protection Act 2023, storage limitation):
 * personal data is not kept longer than needed. Runs daily.
 */
class ApplyRetention extends Command
{
    protected $signature = 'nis:retention {--dry-run : Report what would be removed without removing it}';

    protected $description = 'Delete personal data that is past its retention period';

    public function handle(): int
    {
        $days = config('nis.retention');
        $dry = (bool) $this->option('dry-run');
        $report = [];

        // 1. Applications started but never submitted.
        $drafts = ApplicationDraft::with('documents')->where('updated_at', '<', now()->subDays($days['abandoned_drafts_days']))->get();
        $report['abandoned_drafts'] = $drafts->count();
        if (! $dry) {
            foreach ($drafts as $draft) {
                $this->deleteFiles($draft->documents);
                $draft->documents()->delete();
                $draft->delete();
            }
        }

        // 2. Accounts never verified and never used.
        $accounts = Applicant::whereNull('email_verified_at')->where('created_at', '<', now()->subDays($days['unverified_accounts_days']))
            ->whereDoesntHave('applications')->get();
        $report['unverified_accounts'] = $accounts->count();
        if (! $dry) {
            $accounts->each->delete();
        }

        // 3. Documents of rejected applications (the decision record is kept).
        $rejected = Application::where('status', ApplicationStatus::Rejected)
            ->where('decided_at', '<', now()->subDays($days['rejected_documents_days']))->pluck('id');
        $docs = ApplicationDocument::whereIn('application_id', $rejected)->get();
        $report['rejected_application_documents'] = $docs->count();
        if (! $dry) {
            $this->deleteFiles($docs);
            ApplicationDocument::whereIn('id', $docs->pluck('id'))->delete();
            Application::whereIn('id', $rejected)->update(['photo_path' => null, 'signature_path' => null, 'fingerprint_template' => null]);
        }

        // 4. Payment attempts that were never completed.
        $payments = Payment::where('status', 'INITIALIZED')->where('created_at', '<', now()->subDays($days['unpaid_payments_days']));
        $report['abandoned_payments'] = $payments->count();
        if (! $dry) {
            $payments->delete();
        }

        foreach ($report as $what => $count) {
            $this->line(sprintf('%-32s %d', $what, $count));
        }
        if (! $dry) {
            Audit::log('DATA_RETENTION_APPLIED', 'Retention policy applied', context: $report, actorLabel: 'SYSTEM');
        }

        return self::SUCCESS;
    }

    private function deleteFiles(iterable $documents): void
    {
        foreach ($documents as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }
    }
}
