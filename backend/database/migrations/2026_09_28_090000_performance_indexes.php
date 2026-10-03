<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance: PostgreSQL does not index foreign keys on its own, so the
 * lookups the portal and staff console make (an applicant's applications,
 * an application's history and payments, a card's approvals, ...) and the
 * ON DELETE actions scanned whole tables. Staff name searches use
 * ILIKE '%term%', which only a trigram (pg_trgm) index can serve.
 */
return new class extends Migration
{
    /** Foreign keys used in lookups that no existing index already starts with. */
    private const FOREIGN_KEYS = [
        'applications' => ['applicant_id', 'renewal_of_card_id', 'principal_application_id', 'card_id', 'assigned_to'],
        'application_documents' => ['draft_id'],
        'application_status_histories' => ['application_id'],
        'application_notes' => ['application_id'],
        'payments' => ['applicant_id', 'application_id'],
        'refunds' => ['payment_id', 'applicant_id', 'application_id'],
        'residence_cards' => ['applicant_id'],
        'approval_requests' => ['card_id'],
        'queue_tickets' => ['application_id'],
        'card_print_jobs' => ['batch_id'],
        'push_subscriptions' => ['applicant_id'],
    ];

    /** Columns searched with ILIKE '%term%'. */
    private const TRIGRAM = [
        'applications' => ['surname', 'forenames'],
        'residence_cards' => ['surname', 'forenames'],
        'applicants' => ['surname', 'forenames', 'email'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->index($column);
                }
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Staff search also matches application numbers typed without dashes.
            DB::statement("CREATE INDEX applications_application_number_nodash_index ON applications ((replace(application_number, '-', '')))");

            // Shared hosting may not let the database user add extensions: search
            // then still works, only without these indexes. (A savepoint keeps a
            // refused CREATE EXTENSION from aborting the migration's transaction.)
            try {
                DB::transaction(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'));
            } catch (QueryException) {
            }
            if (! DB::table('pg_extension')->where('extname', 'pg_trgm')->exists()) {
                return;
            }

            foreach (self::TRIGRAM as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("CREATE INDEX {$table}_{$column}_trgm_index ON {$table} USING gin ({$column} gin_trgm_ops)");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS applications_application_number_nodash_index');
            foreach (self::TRIGRAM as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm_index");
                }
            }
        }

        foreach (self::FOREIGN_KEYS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->dropIndex([$column]);
                }
            });
        }
    }
};
