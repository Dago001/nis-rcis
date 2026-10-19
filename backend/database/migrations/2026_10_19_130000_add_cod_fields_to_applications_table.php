<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'has_cod')) {
                $table->boolean('has_cod')->default(false)->after('type');
            }
            if (! Schema::hasColumn('applications', 'cod_fee_kobo')) {
                $table->unsignedBigInteger('cod_fee_kobo')->default(0)->after('has_cod');
            }
            if (! Schema::hasColumn('applications', 'cod_changes')) {
                $table->json('cod_changes')->nullable()->after('cod_fee_kobo');
            }
            if (! Schema::hasColumn('applications', 'cod_status')) {
                $table->string('cod_status', 30)->nullable()->after('cod_changes');
            }
            if (! Schema::hasColumn('applications', 'renewal_source_application_id')) {
                $table->unsignedBigInteger('renewal_source_application_id')->nullable()->after('cod_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'has_cod')) {
                $table->dropColumn('has_cod');
            }
            if (Schema::hasColumn('applications', 'cod_fee_kobo')) {
                $table->dropColumn('cod_fee_kobo');
            }
            if (Schema::hasColumn('applications', 'cod_changes')) {
                $table->dropColumn('cod_changes');
            }
            if (Schema::hasColumn('applications', 'cod_status')) {
                $table->dropColumn('cod_status');
            }
            if (Schema::hasColumn('applications', 'renewal_source_application_id')) {
                $table->dropColumn('renewal_source_application_id');
            }
        });
    }
};
