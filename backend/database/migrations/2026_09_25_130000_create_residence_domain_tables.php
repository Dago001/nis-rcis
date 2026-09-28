<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal particulars captured on both an application and the card
     * issued from it (mirrors the legacy residence card booklet fields).
     */
    private function particulars(Blueprint $table): void
    {
        $table->string('surname', 100);
        $table->string('forenames', 150);
        $table->string('nationality', 100);
        $table->date('date_of_birth');
        $table->string('place_of_birth', 150);
        $table->string('sex', 10);
        $table->string('height', 30)->nullable();
        $table->string('complexion', 50)->nullable();
        $table->string('eye_color', 50)->nullable();
        $table->string('hair_color', 50)->nullable();
        $table->string('distinguished_features', 150)->default('NONE');
        $table->string('blood_group', 20)->default('UNKNOWN');
        $table->string('profession', 150);
        $table->text('domicile');
        $table->text('change_of_address')->nullable();
        $table->string('passport_number', 20)->index();
        $table->date('passport_issue_date')->nullable();
        $table->date('passport_expiry')->nullable();
        $table->string('national_id_number', 50)->nullable();
        $table->string('tax_id_number', 50)->nullable();
        $table->string('emergency_contact_name', 150);
        $table->string('emergency_contact_relation', 50);
        $table->string('emergency_contact_phone', 30);
        $table->text('emergency_contact_address');
    }

    public function up(): void
    {
        // Human-readable numbers come from sequences, never from rand() or
        // "last number + 1" (the legacy system could produce duplicates).
        DB::unprepared('DROP SEQUENCE IF EXISTS application_number_seq; CREATE SEQUENCE application_number_seq START 100001;');
        DB::unprepared('DROP SEQUENCE IF EXISTS card_number_seq; CREATE SEQUENCE card_number_seq START 389108;');

        Schema::create('enrollment_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('state', 50);
            $table->text('address');
            $table->unsignedSmallInteger('daily_capacity')->default(50);
            $table->json('time_slots');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // "Save & exit" drafts of the application wizard (one per applicant).
        Schema::create('application_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('NEW');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->jsonb('data');
            $table->timestamps();
        });

        Schema::create('residence_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_number', 20)->unique();
            $table->string('booklet_number', 30)->unique();
            $table->string('issuing_country', 100)->default('FEDERAL REPUBLIC OF NIGERIA');
            $table->string('statutory_protocol', 150)->default('');
            $table->string('decision_reference', 100)->default('');
            $table->date('decision_date');
            $table->string('approving_authority', 100)->default('COMPTROLLER GENERAL OF IMMIGRATION');
            $this->particulars($table);
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('issuing_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuing_officer_name', 150);
            $table->string('issuing_officer_service_no', 50);
            $table->date('issued_on');
            $table->string('issued_at', 150);
            $table->date('expires_on')->index();
            $table->string('postage_stamp_code', 50)->nullable();
            $table->string('authority_signature', 100)->nullable();
            // APPROVED (awaiting final issuance approval) -> ISSUED -> RENEWED
            // QUERIED (returned for correction) / REVOKED (reinstatable)
            $table->string('status', 20)->default('APPROVED')->index();
            $table->string('verification_token', 64)->unique();
            $table->string('query_reason')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->boolean('is_watchlisted')->default(false)->index();
            $table->string('watchlist_reason')->nullable();
            $table->foreignId('watchlisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('watchlisted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 20)->unique();
            // Unguessable reference printed on slips; required (with the
            // passport number) for public tracking.
            $table->string('reference_number', 32)->unique();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            // NEW | RENEWAL
            $table->string('type', 10)->default('NEW');
            $table->foreignId('renewal_of_card_id')->nullable()->constrained('residence_cards')->nullOnDelete();
            // ONLINE (applicant portal) | ASSISTED (entered by staff for a walk-in)
            $table->string('channel', 10)->default('ONLINE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $this->particulars($table);
            $table->string('phone', 30);
            $table->string('email');

            $table->foreignId('enrollment_center_id')->constrained()->restrictOnDelete();
            $table->date('appointment_date');
            $table->string('appointment_time', 10);

            $table->unsignedBigInteger('fee_amount_kobo');
            // PENDING | PAID | FAILED
            $table->string('payment_status', 10)->default('PENDING');

            // Workflow — see App\Enums\ApplicationStatus
            $table->string('status', 30)->default('PENDING_APPROVAL')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->text('fingerprint_template')->nullable();
            $table->foreignId('biometrics_captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('biometrics_captured_at')->nullable();

            $table->foreignId('card_id')->nullable()->constrained('residence_cards')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['enrollment_center_id', 'appointment_date', 'appointment_time']);
        });

        // Supporting documents and captured photos. Versioned: a re-upload in
        // response to a query adds a new row and retires the previous one.
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('draft_id')->nullable()->constrained('application_drafts')->cascadeOnDelete();
            // photo | passport_copy | residence_visa | quota_approval | domicile_proof | additional
            $table->string('type', 30);
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('sha256', 64);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->nullableMorphs('uploaded_by');
            $table->timestamps();

            $table->index(['application_id', 'type', 'is_current']);
        });

        // Every workflow transition, who made it and why.
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->nullableMorphs('actor');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('PAYSTACK');
            $table->string('reference', 64)->unique();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            // INITIALIZED | SUCCESS | FAILED
            $table->string('status', 15)->default('INITIALIZED')->index();
            $table->string('channel', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->timestamps();
        });

        // Booklet renewal endorsements (legacy renew-card.php).
        Schema::create('card_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('residence_cards')->cascadeOnDelete();
            $table->unsignedSmallInteger('renewal_number');
            $table->date('from_date');
            $table->date('to_date');
            $table->string('renewed_at', 150);
            $table->foreignId('endorsing_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('endorsing_officer', 150);
            $table->string('officer_service_no', 50);
            $table->unsignedBigInteger('fee_paid_kobo')->default(0);
            $table->string('receipt_number', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['card_id', 'renewal_number']);
        });

        // Append-only audit trail.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('actor');
            $table->string('actor_label', 200);
            $table->string('action', 60)->index();
            $table->nullableMorphs('subject');
            $table->text('description')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
        SQL);

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update_delete ON audit_logs; DROP FUNCTION IF EXISTS audit_logs_append_only();');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('card_renewals');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('residence_cards');
        Schema::dropIfExists('application_drafts');
        Schema::dropIfExists('enrollment_centers');
        DB::statement('DROP SEQUENCE IF EXISTS card_number_seq');
        DB::statement('DROP SEQUENCE IF EXISTS application_number_seq');
    }
};
