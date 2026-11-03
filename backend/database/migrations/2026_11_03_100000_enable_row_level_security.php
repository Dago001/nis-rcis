<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables protected by Row-Level Security (RLS).
     */
    private const PROTECTED_TABLES = [
        'applications',
        'applicants',
        'residence_cards',
        'card_distributions',
        'queue_tickets',
        'payments',
        'application_documents',
        'application_document_queries',
        'card_print_jobs',
    ];

    /**
     * Run the migrations.
     * Implements native PostgreSQL Row-Level Security (RLS) policies.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Enable and force RLS on all sensitive domain tables
        foreach (self::PROTECTED_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
        }

        // 1. Applications Policy
        DB::statement(<<<'SQL'
            CREATE POLICY applications_rls_policy ON applications
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND (
                        applicant_id = current_setting('app.current_applicant_id', true)::bigint
                        OR principal_application_id IN (SELECT id FROM applications WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                    )
                )
                OR (
                    coalesce(nullif(current_setting('app.current_zone_id', true), ''), '0')::bigint > 0
                    AND (
                        enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE zone_id = current_setting('app.current_zone_id', true)::bigint)
                        OR actual_enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE zone_id = current_setting('app.current_zone_id', true)::bigint)
                        OR id IN (SELECT application_id FROM card_distributions WHERE destination_zone_id = current_setting('app.current_zone_id', true)::bigint)
                    )
                )
                OR (
                    coalesce(nullif(current_setting('app.current_command_id', true), ''), '0')::bigint > 0
                    AND (
                        enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE command_id = current_setting('app.current_command_id', true)::bigint)
                        OR actual_enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE command_id = current_setting('app.current_command_id', true)::bigint)
                        OR id IN (SELECT application_id FROM card_distributions WHERE destination_command_id = current_setting('app.current_command_id', true)::bigint)
                    )
                )
            );
        SQL);

        // 2. Applicants Policy
        DB::statement(<<<'SQL'
            CREATE POLICY applicants_rls_policy ON applicants
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND id = current_setting('app.current_applicant_id', true)::bigint
                )
                OR (
                    coalesce(nullif(current_setting('app.current_user_role', true), ''), '') != 'applicant'
                    AND id IN (SELECT applicant_id FROM applications)
                )
            );
        SQL);

        // 3. Residence Cards Policy
        DB::statement(<<<'SQL'
            CREATE POLICY residence_cards_rls_policy ON residence_cards
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND applicant_id = current_setting('app.current_applicant_id', true)::bigint
                )
                OR (
                    coalesce(nullif(current_setting('app.current_zone_id', true), ''), '0')::bigint > 0
                    AND (
                        actual_enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE zone_id = current_setting('app.current_zone_id', true)::bigint)
                        OR id IN (SELECT card_id FROM card_distributions WHERE destination_zone_id = current_setting('app.current_zone_id', true)::bigint)
                        OR id IN (SELECT card_id FROM applications WHERE enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE zone_id = current_setting('app.current_zone_id', true)::bigint))
                    )
                )
                OR (
                    coalesce(nullif(current_setting('app.current_command_id', true), ''), '0')::bigint > 0
                    AND (
                        actual_enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE command_id = current_setting('app.current_command_id', true)::bigint)
                        OR id IN (SELECT card_id FROM card_distributions WHERE destination_command_id = current_setting('app.current_command_id', true)::bigint)
                        OR id IN (SELECT card_id FROM applications WHERE enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE command_id = current_setting('app.current_command_id', true)::bigint))
                    )
                )
            );
        SQL);

        // 4. Card Distributions Policy
        DB::statement(<<<'SQL'
            CREATE POLICY card_distributions_rls_policy ON card_distributions
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND application_id IN (SELECT id FROM applications WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                )
                OR (
                    coalesce(nullif(current_setting('app.current_zone_id', true), ''), '0')::bigint > 0
                    AND destination_zone_id = current_setting('app.current_zone_id', true)::bigint
                )
                OR (
                    coalesce(nullif(current_setting('app.current_command_id', true), ''), '0')::bigint > 0
                    AND destination_command_id = current_setting('app.current_command_id', true)::bigint
                )
            );
        SQL);

        // 5. Queue Tickets Policy
        DB::statement(<<<'SQL'
            CREATE POLICY queue_tickets_rls_policy ON queue_tickets
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND application_id IN (SELECT id FROM applications WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                )
                OR (
                    coalesce(nullif(current_setting('app.current_zone_id', true), ''), '0')::bigint > 0
                    AND enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE zone_id = current_setting('app.current_zone_id', true)::bigint)
                )
                OR (
                    coalesce(nullif(current_setting('app.current_command_id', true), ''), '0')::bigint > 0
                    AND enrollment_center_id IN (SELECT id FROM enrollment_centers WHERE command_id = current_setting('app.current_command_id', true)::bigint)
                )
            );
        SQL);

        // 6. Payments Policy
        DB::statement(<<<'SQL'
            CREATE POLICY payments_rls_policy ON payments
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND applicant_id = current_setting('app.current_applicant_id', true)::bigint
                )
                OR (
                    application_id IN (SELECT id FROM applications)
                )
            );
        SQL);

        // 7. Application Documents Policy
        DB::statement(<<<'SQL'
            CREATE POLICY application_documents_rls_policy ON application_documents
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND (
                        application_id IN (SELECT id FROM applications WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                        OR draft_id IN (SELECT id FROM application_drafts WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                    )
                )
                OR (
                    application_id IN (SELECT id FROM applications)
                )
            );
        SQL);

        // 8. Application Document Queries Policy
        DB::statement(<<<'SQL'
            CREATE POLICY application_document_queries_rls_policy ON application_document_queries
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin')
                OR (
                    coalesce(nullif(current_setting('app.current_applicant_id', true), ''), '0')::bigint > 0
                    AND application_id IN (SELECT id FROM applications WHERE applicant_id = current_setting('app.current_applicant_id', true)::bigint)
                )
                OR (
                    application_id IN (SELECT id FROM applications)
                )
            );
        SQL);

        // 9. Card Print Jobs Policy
        DB::statement(<<<'SQL'
            CREATE POLICY card_print_jobs_rls_policy ON card_print_jobs
            FOR ALL
            USING (
                coalesce(nullif(current_setting('app.is_bypass_rls', true), ''), 'false') = 'true'
                OR coalesce(nullif(current_setting('app.current_user_role', true), ''), '') IN ('SuperAdmin', 'HeadquartersAdmin', 'Admin', 'CardProductionOfficer', 'ProductionOfficer')
                OR card_id IN (SELECT id FROM residence_cards)
            );
        SQL);
    }

    /**
     * Reverse the migrations.
     * Safely drops all RLS policies and disables RLS on the tables.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::PROTECTED_TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_rls_policy ON {$table};");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }
    }
};
