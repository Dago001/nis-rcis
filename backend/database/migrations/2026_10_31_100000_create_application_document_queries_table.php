<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_document_queries')) {
            Schema::create('application_document_queries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('application_documents')->cascadeOnDelete();
                $table->string('document_type', 50);
                $table->string('stage', 20)->default('VETTING'); // VETTING | APPROVAL
                $table->text('query_reason');
                $table->string('status', 30)->default('QUERY_RAISED'); // QUERY_RAISED | AWAITING_RESPONSE | RESPONSE_SUBMITTED | RESOLVED
                $table->foreignId('queried_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('queried_at')->useCurrent();
                $table->text('applicant_response')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('replacement_document_id')->nullable()->constrained('application_documents')->nullOnDelete();
                $table->timestamps();

                $table->index(['application_id', 'status']);
                $table->index(['document_id', 'status']);
                $table->index(['stage', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_document_queries');
    }
};
