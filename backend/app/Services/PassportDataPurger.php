<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDraft;
use App\Models\ApplicationNote;
use App\Models\ApplicationStatusHistory;
use App\Models\CardPrintJob;
use App\Models\CardRenewal;
use App\Models\IntegrationCheck;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PassportDataPurger
{
    /**
     * Completely remove a passport number, specific application number, and all related
     * entities from the database, storage, and system.
     *
     * @param string $passportNumber
     * @param string|null $applicationNumber
     * @return array Summary of purged records
     */
    public static function purge(string $passportNumber, ?string $applicationNumber = null): array
    {
        $passportNumber = strtoupper(trim($passportNumber));
        if ($applicationNumber) {
            $applicationNumber = strtoupper(trim($applicationNumber));
        }

        $summary = [
            'passport' => $passportNumber,
            'application_number' => $applicationNumber,
            'applications_deleted' => 0,
            'cards_deleted' => 0,
            'documents_deleted' => 0,
            'status_histories_deleted' => 0,
            'notes_deleted' => 0,
            'integration_checks_deleted' => 0,
            'queue_tickets_deleted' => 0,
            'payments_deleted' => 0,
            'card_renewals_deleted' => 0,
            'card_print_jobs_deleted' => 0,
            'drafts_deleted' => 0,
            'notifications_deleted' => 0,
            'applicants_updated' => 0,
        ];

        // 1. Gather all matching applications
        $appQuery = Application::query()->where('passport_number', $passportNumber);
        if ($applicationNumber) {
            $appQuery->orWhere('application_number', $applicationNumber);
        }
        $applications = $appQuery->get();
        $applicationIds = $applications->pluck('id')->filter()->unique()->all();

        // 2. Gather all matching residence cards
        $cardQuery = ResidenceCard::query()->where('passport_number', $passportNumber);
        if ($applicationNumber) {
            $cardQuery->orWhere('decision_reference', $applicationNumber);
        }
        if (! empty($applicationIds)) {
            $appCardIds = $applications->pluck('card_id')->filter()->all();
            $renewalCardIds = $applications->pluck('renewal_of_card_id')->filter()->all();
            $linkedCardIds = array_unique(array_merge($appCardIds, $renewalCardIds));
            if (! empty($linkedCardIds)) {
                $cardQuery->orWhereIn('id', $linkedCardIds);
            }
        }
        $cards = $cardQuery->get();
        $cardIds = $cards->pluck('id')->filter()->unique()->all();

        // 3. Clear foreign keys on other applications referencing these applications or cards
        if (! empty($applicationIds)) {
            Application::whereIn('principal_application_id', $applicationIds)
                ->update(['principal_application_id' => null]);

            Application::whereIn('id', $applicationIds)
                ->update([
                    'principal_application_id' => null,
                    'card_id' => null,
                    'renewal_of_card_id' => null,
                ]);
        }

        if (! empty($cardIds)) {
            Application::whereIn('card_id', $cardIds)
                ->update(['card_id' => null]);

            Application::whereIn('renewal_of_card_id', $cardIds)
                ->update(['renewal_of_card_id' => null]);
        }

        // 4. Delete documents and physical files
        if (! empty($applicationIds)) {
            $docs = ApplicationDocument::whereIn('application_id', $applicationIds)->get();
            foreach ($docs as $doc) {
                if ($doc->path && $doc->disk) {
                    try {
                        Storage::disk($doc->disk)->delete($doc->path);
                    } catch (\Throwable $e) {
                        Log::warning("PassportDataPurger: Failed to delete doc file {$doc->path}: {$e->getMessage()}");
                    }
                }
            }
            $summary['documents_deleted'] += ApplicationDocument::whereIn('application_id', $applicationIds)->delete();
        }

        // 5. Delete application-linked child tables
        if (! empty($applicationIds)) {
            $summary['status_histories_deleted'] = ApplicationStatusHistory::whereIn('application_id', $applicationIds)->delete();
            $summary['notes_deleted'] = ApplicationNote::whereIn('application_id', $applicationIds)->delete();
            $summary['integration_checks_deleted'] = IntegrationCheck::whereIn('application_id', $applicationIds)->delete();
            $summary['queue_tickets_deleted'] = QueueTicket::whereIn('application_id', $applicationIds)->delete();
            $summary['payments_deleted'] = Payment::whereIn('application_id', $applicationIds)->delete();
        }

        // 6. Delete card print jobs, renewals, and card photo/signature files
        if (! empty($cardIds)) {
            $summary['card_print_jobs_deleted'] = CardPrintJob::whereIn('card_id', $cardIds)->delete();
            $summary['card_renewals_deleted'] = CardRenewal::whereIn('card_id', $cardIds)->delete();

            foreach ($cards as $card) {
                foreach ([$card->photo_path, $card->signature_path] as $filePath) {
                    if ($filePath) {
                        try {
                            Storage::disk('public')->delete($filePath);
                        } catch (\Throwable $e) {}
                    }
                }
            }
            $summary['cards_deleted'] = ResidenceCard::whereIn('id', $cardIds)->delete();
        }

        // Direct DB cleanup for any orphaned residence cards
        $directCards = DB::table('residence_cards')->where('passport_number', $passportNumber);
        if ($applicationNumber) {
            $directCards->orWhere('decision_reference', $applicationNumber);
        }
        $directCards->delete();

        // 7. Delete applications
        if (! empty($applicationIds)) {
            $summary['applications_deleted'] = Application::whereIn('id', $applicationIds)->delete();
        }

        // Direct DB cleanup for any orphaned application records
        $directApps = DB::table('applications')->where('passport_number', $passportNumber);
        if ($applicationNumber) {
            $directApps->orWhere('application_number', $applicationNumber);
        }
        $directApps->delete();

        // 8. Clear passport number from applicant account profiles
        $summary['applicants_updated'] = Applicant::where('passport_number', $passportNumber)
            ->update(['passport_number' => null]);

        // 9. Clean application drafts
        try {
            $drafts = ApplicationDraft::all();
            foreach ($drafts as $draft) {
                $json = json_encode($draft->data ?? []);
                if (str_contains($json, $passportNumber) || ($applicationNumber && str_contains($json, $applicationNumber))) {
                    $draftDocs = ApplicationDocument::where('draft_id', $draft->id)->get();
                    foreach ($draftDocs as $dDoc) {
                        if ($dDoc->path && $dDoc->disk) {
                            try {
                                Storage::disk($dDoc->disk)->delete($dDoc->path);
                            } catch (\Throwable $e) {}
                        }
                    }
                    ApplicationDocument::where('draft_id', $draft->id)->delete();
                    $draft->delete();
                    $summary['drafts_deleted']++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("PassportDataPurger: Draft cleanup error: {$e->getMessage()}");
        }

        // 10. Clean notifications
        try {
            $notifications = DB::table('notifications')->get();
            $deleteNotifIds = [];
            foreach ($notifications as $n) {
                $payload = (string) ($n->data ?? '');
                if (str_contains($payload, $passportNumber) || ($applicationNumber && str_contains($payload, $applicationNumber))) {
                    $deleteNotifIds[] = $n->id;
                }
            }
            if (! empty($deleteNotifIds)) {
                $summary['notifications_deleted'] = DB::table('notifications')->whereIn('id', $deleteNotifIds)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning("PassportDataPurger: Notification cleanup error: {$e->getMessage()}");
        }

        // 11. Clean audit logs if database permissions allow (safe against append-only triggers)
        try {
            DB::table('audit_logs')
                ->where('description', 'LIKE', "%{$passportNumber}%")
                ->orWhere('description', 'LIKE', "%{$applicationNumber}%")
                ->delete();
        } catch (\Throwable $e) {
            // Append-only trigger on PostgreSQL will reject deletion; gracefully skip.
        }

        Log::info("PassportDataPurger: Purge complete for {$passportNumber}", $summary);

        return $summary;
    }
}
