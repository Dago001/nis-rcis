<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External checks (Interpol stolen and lost travel documents, Ministry of
 * Interior expatriate quota register) and fingerprints from the scanner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('quota_reference', 60)->nullable();   // expatriate quota approval number
            $table->string('employer_name', 150)->nullable();
            $table->text('fingerprints')->nullable();            // encrypted JSON: finger, template, format, quality
        });

        Schema::create('integration_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('service', 30);                         // INTERPOL_SLTD | MOI_QUOTA
            $table->string('status', 12);                          // CLEAR | HIT | VALID | INVALID | NOT_FOUND | ERROR | DISABLED
            $table->string('reference', 100)->nullable();          // the other system's transaction/record id
            $table->string('summary', 255);
            $table->jsonb('details')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['application_id', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_checks');
        Schema::table('applications', fn (Blueprint $t) => $t->dropColumn(['quota_reference', 'employer_name', 'fingerprints']));
    }
};
