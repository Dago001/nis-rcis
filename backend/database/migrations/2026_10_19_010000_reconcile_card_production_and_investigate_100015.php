<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ResidenceCard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reconcile card production eligibility, verify application RC-2026-100015 (Passport A34545454),
     * and ensure relational consistency between applications and residence cards.
     */
    public function up(): void
    {
        // 1. Audit and investigate application RC-2026-100015 / ROC 2026 100015 (Passport A34545454)
        $targetApp = Application::query()
            ->where('passport_number', 'A34545454')
            ->orWhere('application_number', 'RC-2026-100015')
            ->orWhere('application_number', 'ROC-2026-100015')
            ->orWhere('application_number', 'ROC 2026 100015')
            ->first();

        if ($targetApp) {
            $matchingCard = ResidenceCard::query()
                ->where('passport_number', 'A34545454')
                ->orWhere('decision_reference', $targetApp->application_number)
                ->first();

            // Link card if exists and unlinked
            if ($matchingCard && ! $targetApp->card_id) {
                $targetApp->card_id = $matchingCard->id;
                if ($targetApp->biometrics_captured_at === null && $matchingCard->issued_on) {
                    $targetApp->biometrics_captured_at = $matchingCard->created_at ?? now();
                }
                $targetApp->save();
            }

            Log::info('Audit 100015 completed', [
                'id' => $targetApp->id,
                'application_number' => $targetApp->application_number,
                'passport_number' => $targetApp->passport_number,
                'status' => $targetApp->status?->value ?? (string) $targetApp->status,
                'vetted_at' => $targetApp->vetted_at,
                'decided_at' => $targetApp->decided_at,
                'biometrics_captured_at' => $targetApp->biometrics_captured_at,
                'has_card' => (bool) $targetApp->card_id,
            ]);
        }

        // 2. Reconcile any unlinked cards with matching applications
        $unlinkedApps = Application::query()
            ->whereNull('card_id')
            ->whereNotNull('biometrics_captured_at')
            ->get();

        foreach ($unlinkedApps as $app) {
            $card = ResidenceCard::query()
                ->where('decision_reference', $app->application_number)
                ->orWhere(function ($q) use ($app) {
                    $q->where('passport_number', $app->passport_number)
                      ->where('surname', $app->surname);
                })
                ->first();

            if ($card) {
                $app->card_id = $card->id;
                $app->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data reconciliation does not require rollback.
    }
};
