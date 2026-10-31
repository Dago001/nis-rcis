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
use App\Models\Refund;
use App\Models\ResidenceCard;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TestAccountResetter
{
    public const TARGET_EMAILS = [
        'blessingeze00034@gmail.com',
        'ezeab.nis@gmail.com',
    ];

    public const SETTING_KEY = 'test_accounts_purged_2026_10_19';

    /**
     * Check if old test records exist that require resetting.
     */
    public static function shouldReset(): bool
    {
        try {
            if (SystemSetting::get(self::SETTING_KEY, false)) {
                return false;
            }

            $hasTargetApps = Application::query()
                ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(email)'), self::TARGET_EMAILS)
                      ->orWhereHas('applicant', function ($sub) {
                          $sub->whereIn(DB::raw('LOWER(email)'), self::TARGET_EMAILS);
                      });
                })
                ->exists();

            if ($hasTargetApps) {
                return true;
            }

            $applicantIds = Applicant::whereIn(DB::raw('LOWER(email)'), self::TARGET_EMAILS)->pluck('id')->all();
            if (! empty($applicantIds)) {
                $hasDrafts = ApplicationDraft::whereIn('applicant_id', $applicantIds)->exists();
                if ($hasDrafts) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('TestAccountResetter: shouldReset check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Inspect all test application data, drafts, cards, and associated records
     * without modifying or deleting anything.
     */
    public static function inspect(array $emails = ['ezeab.nis@gmail.com']): array
    {
        $targetEmails = array_map('strtolower', $emails);

        $applicants = Applicant::whereIn(DB::raw('LOWER(email)'), $targetEmails)->get();
        $applicantIds = $applicants->pluck('id')->filter()->unique()->all();

        $appQuery = Application::query()->where(function ($q) use ($applicantIds, $targetEmails) {
            if (! empty($applicantIds)) {
                $q->whereIn('applicant_id', $applicantIds);
            }
            $q->orWhereIn(DB::raw('LOWER(email)'), $targetEmails);
        });

        $allApps = $appQuery->get();
        $appIds = $allApps->pluck('id')->filter()->unique()->all();

        $foundMore = true;
        while ($foundMore && ! empty($appIds)) {
            $dependents = Application::whereIn('principal_application_id', $appIds)
                ->whereNotIn('id', $appIds)
                ->get();

            if ($dependents->isEmpty()) {
                $foundMore = false;
            } else {
                foreach ($dependents as $dep) {
                    $allApps->push($dep);
                    $appIds[] = $dep->id;
                }
            }
        }
        $appIds = array_values(array_unique($appIds));
        $appNumbers = $allApps->pluck('application_number')->filter()->unique()->all();
        $passports = $allApps->pluck('passport_number')->filter()->unique()->all();

        $cardQuery = ResidenceCard::query();
        $hasCardConditions = false;

        if (! empty($applicantIds)) {
            $cardQuery->whereIn('applicant_id', $applicantIds);
            $hasCardConditions = true;
        }
        if (! empty($appNumbers)) {
            $cardQuery->orWhereIn('decision_reference', $appNumbers);
            $hasCardConditions = true;
        }
        if (! empty($passports)) {
            $cardQuery->orWhereIn('passport_number', $passports);
            $hasCardConditions = true;
        }
        if (! empty($appIds)) {
            $linkedCardIds = $allApps->pluck('card_id')->filter()
                ->merge($allApps->pluck('renewal_of_card_id')->filter())
                ->unique()->all();
            if (! empty($linkedCardIds)) {
                $cardQuery->orWhereIn('id', $linkedCardIds);
                $hasCardConditions = true;
            }
        }

        $cards = $hasCardConditions ? $cardQuery->get() : collect();
        $cardIds = $cards->pluck('id')->filter()->unique()->all();

        $draftQuery = ApplicationDraft::query();
        if (! empty($applicantIds)) {
            $draftQuery->whereIn('applicant_id', $applicantIds);
        }
        $drafts = $draftQuery->get();
        $draftIds = $drafts->pluck('id')->all();

        $docsCount = ! empty($appIds) ? ApplicationDocument::whereIn('application_id', $appIds)->count() : 0;
        if (! empty($draftIds)) {
            $docsCount += ApplicationDocument::whereIn('draft_id', $draftIds)->count();
        }

        $documentQueriesCount = 0;
        if (! empty($appIds) && Schema::hasTable('application_document_queries')) {
            $documentQueriesCount = DB::table('application_document_queries')->whereIn('application_id', $appIds)->count();
        }

        $statusHistoriesCount = ! empty($appIds) ? ApplicationStatusHistory::whereIn('application_id', $appIds)->count() : 0;
        $notesCount = ! empty($appIds) ? ApplicationNote::whereIn('application_id', $appIds)->count() : 0;
        $integrationChecksCount = ! empty($appIds) ? IntegrationCheck::whereIn('application_id', $appIds)->count() : 0;
        $queueTicketsCount = ! empty($appIds) ? QueueTicket::whereIn('application_id', $appIds)->count() : 0;
        $refundsCount = ! empty($appIds) ? Refund::whereIn('application_id', $appIds)->count() : 0;
        $paymentsCount = ! empty($appIds) ? Payment::whereIn('application_id', $appIds)->count() : 0;
        if (! empty($applicantIds)) {
            $paymentsCount += Payment::whereIn('applicant_id', $applicantIds)->whereNotIn('application_id', $appIds)->count();
        }

        $cardPrintJobsCount = ! empty($cardIds) ? CardPrintJob::whereIn('card_id', $cardIds)->count() : 0;
        $cardRenewalsCount = ! empty($cardIds) ? CardRenewal::whereIn('card_id', $cardIds)->count() : 0;

        $notificationsCount = 0;
        if (! empty($applicantIds)) {
            $notificationsCount = DB::table('notifications')
                ->where('notifiable_type', 'App\Models\Applicant')
                ->whereIn('notifiable_id', $applicantIds)
                ->count();
        }

        return [
            'target_emails' => $targetEmails,
            'environment' => config('app.env'),
            'applicants' => $applicants->map(fn ($a) => [
                'id' => $a->id,
                'email' => $a->email,
                'surname' => $a->surname,
                'forenames' => $a->forenames,
                'status' => 'LOGIN PRESERVED (WILL NOT BE DELETED)',
            ])->all(),
            'counts' => [
                'applications' => count($appIds),
                'drafts' => count($draftIds),
                'documents' => $docsCount,
                'document_queries' => $documentQueriesCount,
                'status_histories' => $statusHistoriesCount,
                'notes' => $notesCount,
                'integration_checks' => $integrationChecksCount,
                'queue_tickets' => $queueTicketsCount,
                'payments' => $paymentsCount,
                'residence_cards' => count($cardIds),
                'card_print_jobs' => $cardPrintJobsCount,
                'card_renewals' => $cardRenewalsCount,
                'notifications' => $notificationsCount,
            ],
            'applications' => $allApps->map(fn ($app) => [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'type' => $app->type,
                'status' => is_string($app->status) ? $app->status : ($app->status->value ?? (string) $app->status),
                'category' => $app->category,
                'submitted_at' => $app->submitted_at?->toIso8601String(),
            ])->all(),
            'cards' => $cards->map(fn ($c) => [
                'id' => $c->id,
                'card_number' => $c->card_number,
                'status' => is_string($c->status) ? $c->status : ($c->status->value ?? (string) $c->status),
            ])->all(),
        ];
    }

    /**
     * Delete all test application data, drafts, cards, and associated records
     * for the specified test accounts without creating backups.
     */
    public static function reset(?array $emails = null): array
    {
        $targetEmails = array_map('strtolower', $emails ?? self::TARGET_EMAILS);

        $summary = [
            'target_emails' => $targetEmails,
            'applicants_preserved' => 0,
            'applications_deleted' => 0,
            'drafts_deleted' => 0,
            'documents_deleted' => 0,
            'document_queries_deleted' => 0,
            'status_histories_deleted' => 0,
            'notes_deleted' => 0,
            'integration_checks_deleted' => 0,
            'queue_tickets_deleted' => 0,
            'refunds_deleted' => 0,
            'payments_deleted' => 0,
            'approval_requests_deleted' => 0,
            'cards_deleted' => 0,
            'card_print_jobs_deleted' => 0,
            'card_renewals_deleted' => 0,
            'notifications_deleted' => 0,
            'files_removed' => 0,
        ];

        // 1. Locate applicant accounts
        $applicants = Applicant::whereIn(DB::raw('LOWER(email)'), $targetEmails)->get();
        $applicantIds = $applicants->pluck('id')->filter()->unique()->all();

        // 2. Identify all applications belonging to these applicants or target emails
        $appQuery = Application::query()->where(function ($q) use ($applicantIds, $targetEmails) {
            if (! empty($applicantIds)) {
                $q->whereIn('applicant_id', $applicantIds);
            }
            $q->orWhereIn(DB::raw('LOWER(email)'), $targetEmails);
        });

        $allApps = $appQuery->get();
        $appIds = $allApps->pluck('id')->filter()->unique()->all();

        // Recursively find linked dependent applications
        $foundMore = true;
        while ($foundMore && ! empty($appIds)) {
            $dependents = Application::whereIn('principal_application_id', $appIds)
                ->whereNotIn('id', $appIds)
                ->get();

            if ($dependents->isEmpty()) {
                $foundMore = false;
            } else {
                foreach ($dependents as $dep) {
                    $allApps->push($dep);
                    $appIds[] = $dep->id;
                }
            }
        }
        $appIds = array_unique($appIds);

        $appNumbers = $allApps->pluck('application_number')->filter()->unique()->all();
        $passports = $allApps->pluck('passport_number')->filter()->unique()->all();

        // 3. Identify all residence cards linked to these applications or applicants
        $cardQuery = ResidenceCard::query();
        $hasCardConditions = false;

        if (! empty($applicantIds)) {
            $cardQuery->whereIn('applicant_id', $applicantIds);
            $hasCardConditions = true;
        }
        if (! empty($appNumbers)) {
            $cardQuery->orWhereIn('decision_reference', $appNumbers);
            $hasCardConditions = true;
        }
        if (! empty($passports)) {
            $cardQuery->orWhereIn('passport_number', $passports);
            $hasCardConditions = true;
        }
        if (! empty($appIds)) {
            $linkedCardIds = $allApps->pluck('card_id')->filter()
                ->merge($allApps->pluck('renewal_of_card_id')->filter())
                ->unique()->all();
            if (! empty($linkedCardIds)) {
                $cardQuery->orWhereIn('id', $linkedCardIds);
                $hasCardConditions = true;
            }
        }

        $cards = $hasCardConditions ? $cardQuery->get() : collect();
        $cardIds = $cards->pluck('id')->filter()->unique()->all();

        // 4. Sever foreign keys to avoid constraint violations
        if (! empty($appIds)) {
            Application::whereIn('principal_application_id', $appIds)
                ->update(['principal_application_id' => null]);

            Application::whereIn('id', $appIds)
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

        // 5. Delete application drafts and draft documents
        $draftQuery = ApplicationDraft::query();
        if (! empty($applicantIds)) {
            $draftQuery->whereIn('applicant_id', $applicantIds);
        }
        $drafts = $draftQuery->get();
        $draftIds = $drafts->pluck('id')->all();

        try {
            $allDrafts = ApplicationDraft::all();
            foreach ($allDrafts as $d) {
                if (! in_array($d->id, $draftIds, true)) {
                    $json = strtolower(json_encode($d->data ?? []));
                    foreach ($targetEmails as $em) {
                        if (str_contains($json, $em)) {
                            $drafts->push($d);
                            $draftIds[] = $d->id;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $draftIds = array_unique($draftIds);

        if (! empty($draftIds)) {
            $draftDocs = ApplicationDocument::whereIn('draft_id', $draftIds)->get();
            foreach ($draftDocs as $dDoc) {
                if ($dDoc->path && $dDoc->disk) {
                    try {
                        Storage::disk($dDoc->disk)->delete($dDoc->path);
                        $summary['files_removed']++;
                    } catch (\Throwable $e) {}
                }
            }
            $summary['documents_deleted'] += ApplicationDocument::whereIn('draft_id', $draftIds)->delete();
            $summary['drafts_deleted'] = ApplicationDraft::whereIn('id', $draftIds)->delete();
        }

        // 6. Delete supporting documents for submitted applications
        if (! empty($appIds)) {
            $docs = ApplicationDocument::whereIn('application_id', $appIds)->get();
            foreach ($docs as $doc) {
                if ($doc->path && $doc->disk) {
                    try {
                        Storage::disk($doc->disk)->delete($doc->path);
                        $summary['files_removed']++;
                    } catch (\Throwable $e) {}
                }
            }
            $summary['documents_deleted'] += ApplicationDocument::whereIn('application_id', $appIds)->delete();
        }

        // 7. Delete application child records
        if (! empty($appIds)) {
            if (Schema::hasTable('application_document_queries')) {
                $summary['document_queries_deleted'] = DB::table('application_document_queries')->whereIn('application_id', $appIds)->delete();
            }
            $summary['status_histories_deleted'] = ApplicationStatusHistory::whereIn('application_id', $appIds)->delete();
            $summary['notes_deleted'] = ApplicationNote::whereIn('application_id', $appIds)->delete();
            $summary['integration_checks_deleted'] = IntegrationCheck::whereIn('application_id', $appIds)->delete();
            $summary['queue_tickets_deleted'] = QueueTicket::whereIn('application_id', $appIds)->delete();
            $summary['refunds_deleted'] = Refund::whereIn('application_id', $appIds)->delete();
            $summary['payments_deleted'] = Payment::whereIn('application_id', $appIds)->delete();
        }

        if (! empty($applicantIds)) {
            $summary['payments_deleted'] += Payment::whereIn('applicant_id', $applicantIds)->delete();
        }

        // 8. Delete card print jobs, renewals, approval requests, and cards
        if (! empty($cardIds)) {
            $summary['card_print_jobs_deleted'] = CardPrintJob::whereIn('card_id', $cardIds)->delete();
            $summary['card_renewals_deleted'] = CardRenewal::whereIn('card_id', $cardIds)->delete();

            if (Schema::hasTable('approval_requests')) {
                $summary['approval_requests_deleted'] = DB::table('approval_requests')->whereIn('card_id', $cardIds)->delete();
            }

            foreach ($cards as $card) {
                foreach ([$card->photo_path, $card->signature_path] as $filePath) {
                    if ($filePath) {
                        try {
                            Storage::disk('public')->delete($filePath);
                            $summary['files_removed']++;
                        } catch (\Throwable $e) {}
                    }
                }
            }
            $summary['cards_deleted'] = ResidenceCard::whereIn('id', $cardIds)->delete();
        }

        // 9. Delete applications
        if (! empty($appIds)) {
            foreach ($allApps as $app) {
                foreach ([$app->photo_path, $app->signature_path] as $filePath) {
                    if ($filePath) {
                        try {
                            Storage::disk('public')->delete($filePath);
                            $summary['files_removed']++;
                        } catch (\Throwable $e) {}
                    }
                }
            }
            $summary['applications_deleted'] = Application::whereIn('id', $appIds)->delete();
        }

        // Direct database cleanup for any leftover application or card records matching emails or numbers
        DB::table('applications')->whereIn(DB::raw('LOWER(email)'), $targetEmails)->delete();
        if (! empty($appNumbers)) {
            DB::table('applications')->whereIn('application_number', $appNumbers)->delete();
            DB::table('residence_cards')->whereIn('decision_reference', $appNumbers)->delete();
        }
        if (! empty($passports)) {
            DB::table('residence_cards')->whereIn('passport_number', $passports)->delete();
        }

        // 10. Delete notifications
        if (! empty($applicantIds)) {
            $summary['notifications_deleted'] = DB::table('notifications')
                ->where('notifiable_type', 'App\Models\Applicant')
                ->whereIn('notifiable_id', $applicantIds)
                ->delete();
        }

        try {
            $notifs = DB::table('notifications')->get();
            $deleteNotifIds = [];
            foreach ($notifs as $n) {
                $payload = strtolower((string) ($n->data ?? ''));
                foreach (array_merge($targetEmails, $appNumbers) as $needle) {
                    if (! empty($needle) && str_contains($payload, strtolower($needle))) {
                        $deleteNotifIds[] = $n->id;
                        break;
                    }
                }
            }
            if (! empty($deleteNotifIds)) {
                $summary['notifications_deleted'] += DB::table('notifications')->whereIn('id', $deleteNotifIds)->delete();
            }
        } catch (\Throwable $e) {}

        // 11. Preserve applicant accounts and ensure both exist and have cleared passport references
        foreach ($targetEmails as $email) {
            $applicant = Applicant::where('email', $email)->first();
            if ($applicant) {
                $applicant->update([
                    'passport_number' => null,
                ]);
                $summary['applicants_preserved']++;
            } else {
                // Ensure the account exists so the user can log in immediately for fresh testing
                $applicant = Applicant::create([
                    'email' => $email,
                    'password' => Hash::make('Password123!'),
                    'surname' => 'EZE',
                    'forenames' => 'TESTING',
                    'phone' => '+2348030000000',
                    'email_verified_at' => now(),
                    'privacy_consent_at' => now(),
                    'privacy_policy_version' => config('nis.privacy_policy_version', '2026-09'),
                ]);
                $summary['applicants_preserved']++;
            }
        }

        // 12. Record setting to prevent repeated purges
        SystemSetting::set(self::SETTING_KEY, now()->toIso8601String());

        // 13. Clear caches
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {}

        Log::info('TestAccountResetter: Complete test data reset finished successfully', $summary);

        return $summary;
    }
}
