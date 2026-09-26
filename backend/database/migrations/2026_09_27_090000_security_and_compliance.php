<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security & compliance: staff two-factor sign-in, fraud/duplicate flags,
 * the two-person rule for sensitive card actions, and Nigeria Data
 * Protection Act 2023 records (consent, breach register).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();          // encrypted at rest
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // replay protection
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->jsonb('risk_flags')->nullable();
            $table->timestamp('risk_checked_at')->nullable();
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->timestamp('privacy_consent_at')->nullable();
            $table->string('privacy_policy_version', 20)->nullable();
        });

        // Two-person rule: a second officer must approve these actions.
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('action', 30);                 // CARD_REVOKE | CARD_REINSTATE | CARD_UPDATE
            $table->foreignId('card_id')->constrained('residence_cards')->cascadeOnDelete();
            $table->jsonb('payload')->nullable();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 12)->default('PENDING')->index(); // PENDING | APPROVED | REJECTED | CANCELLED
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        // NDPA 2023 personal-data breach register.
        Schema::create('data_breaches', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->string('severity', 10);               // LOW | MEDIUM | HIGH | CRITICAL
            $table->string('status', 12)->default('OPEN'); // OPEN | CONTAINED | CLOSED
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('detected_at');
            $table->string('data_categories', 300)->nullable();
            $table->unsignedInteger('affected_count')->nullable();
            $table->text('containment_actions')->nullable();
            $table->timestamp('regulator_notified_at')->nullable();   // NDPC: within 72 hours
            $table->timestamp('subjects_notified_at')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
        Schema::dropIfExists('approval_requests');
        Schema::table('applicants', fn (Blueprint $t) => $t->dropColumn(['privacy_consent_at', 'privacy_policy_version']));
        Schema::table('applications', fn (Blueprint $t) => $t->dropColumn(['risk_flags', 'risk_checked_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step']));
    }
};
