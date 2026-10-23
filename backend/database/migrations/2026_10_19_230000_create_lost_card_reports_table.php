<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lost_card_reports')) {
            Schema::create('lost_card_reports', function (Blueprint $table) {
                $table->id();
                $table->string('report_reference', 40)->unique();
                $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
                $table->foreignId('card_id')->constrained('residence_cards')->cascadeOnDelete();
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->string('report_type', 20)->default('LOST'); // LOST | STOLEN
                $table->date('incident_date')->nullable();
                $table->string('incident_location', 255)->nullable();
                $table->text('details');
                $table->string('police_report_number', 100)->nullable();
                $table->string('supporting_document_path', 500)->nullable();
                $table->string('status', 30)->default('SUBMITTED'); // SUBMITTED | UNDER_REVIEW | ADDITIONAL_INFO_REQUIRED | RESOLVED | CLOSED
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_notes')->nullable();
                $table->json('action_history')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['applicant_id', 'created_at']);
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared('CREATE SEQUENCE IF NOT EXISTS lost_card_report_seq START 100001;');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_card_reports');
    }
};
