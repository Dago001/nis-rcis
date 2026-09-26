<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff operations: the enrollment-centre queue, blank card stock and the
 * print log, internal notes and assignment, and saved report filters.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Enrollment-centre queue (check-in by slip barcode, walk-ins, "now serving").
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_center_id')->constrained();
            $table->date('service_date');
            $table->string('ticket_number', 8);            // A001 (appointment), W001 (walk-in)
            $table->string('kind', 12);                    // APPOINTMENT | WALK_IN
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('purpose', 150)->nullable();
            $table->string('status', 10)->default('WAITING')->index(); // WAITING | CALLED | DONE | NO_SHOW
            $table->string('desk', 20)->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('checked_in_by')->constrained('users');
            $table->foreignId('served_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['enrollment_center_id', 'service_date', 'ticket_number']);
        });

        // Blank card stock received, and every card printed or spoiled.
        Schema::create('card_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->unsignedInteger('quantity');
            $table->string('serial_from', 30)->nullable();
            $table->string('serial_to', 30)->nullable();
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('card_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('residence_cards')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('card_stock_batches')->nullOnDelete();
            $table->string('outcome', 10);                 // PRINTED | SPOILED
            $table->string('spoil_reason', 255)->nullable();
            $table->foreignId('printed_by')->constrained('users');
            $table->timestamps();
            $table->index(['card_id', 'outcome']);
        });

        // Internal notes (never shown to the applicant) and assignment.
        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
        });

        Schema::create('saved_report_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->jsonb('filters');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_report_filters');
        Schema::table('applications', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_to');
            $t->dropColumn('assigned_at');
        });
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('card_print_jobs');
        Schema::dropIfExists('card_stock_batches');
        Schema::dropIfExists('queue_tickets');
    }
};
