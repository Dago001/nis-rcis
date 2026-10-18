<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'vetted_by')) {
                $table->foreignId('vetted_by')->nullable()->after('decided_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('applications', 'vetted_at')) {
                $table->timestamp('vetted_at')->nullable()->after('decided_at');
            }
            if (! Schema::hasColumn('applications', 'vetting_notes')) {
                $table->text('vetting_notes')->nullable()->after('decision_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'vetted_by')) {
                $table->dropConstrainedForeignId('vetted_by');
            }
            if (Schema::hasColumn('applications', 'vetted_at')) {
                $table->dropColumn('vetted_at');
            }
            if (Schema::hasColumn('applications', 'vetting_notes')) {
                $table->dropColumn('vetting_notes');
            }
        });
    }
};
