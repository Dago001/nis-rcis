<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant features: dependants, lost/stolen card reports, e-mail
 * reminders, and fee refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // A spouse's or child's application, linked to the principal applicant's.
            $table->foreignId('principal_application_id')->nullable()->after('renewal_of_card_id')->constrained('applications')->nullOnDelete();
            $table->string('dependant_relationship', 20)->nullable()->after('principal_application_id');
            $table->timestamp('appointment_reminder_sent_at')->nullable();
        });

        Schema::table('residence_cards', function (Blueprint $table) {
            $table->timestamp('reported_lost_at')->nullable();
            $table->string('lost_report_type', 10)->nullable();        // LOST | STOLEN
            $table->text('lost_report_details')->nullable();
            $table->string('police_report_number', 60)->nullable();
            $table->unsignedSmallInteger('expiry_reminder_days')->nullable(); // last expiry reminder sent (90/30/7)
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->text('reason');
            // REQUESTED | APPROVED | REJECTED | PROCESSED | FAILED
            $table->string('status', 12)->default('REQUESTED')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::table('residence_cards', fn (Blueprint $t) => $t->dropColumn(['reported_lost_at', 'lost_report_type', 'lost_report_details', 'police_report_number', 'expiry_reminder_days']));
        Schema::table('applications', function (Blueprint $t) {
            $t->dropConstrainedForeignId('principal_application_id');
            $t->dropColumn(['dependant_relationship', 'appointment_reminder_sent_at']);
        });
    }
};
