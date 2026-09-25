<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nigerian state and local government area of the applicant's residence
 * and of the emergency contact (picked from dropdowns in the wizard).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'residence_cards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('domicile_state', 60)->nullable()->after('domicile');
                $table->string('domicile_lga', 80)->nullable()->after('domicile_state');
                $table->string('emergency_contact_state', 60)->nullable()->after('emergency_contact_address');
                $table->string('emergency_contact_lga', 80)->nullable()->after('emergency_contact_state');
            });
        }
    }

    public function down(): void
    {
        foreach (['applications', 'residence_cards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['domicile_state', 'domicile_lga', 'emergency_contact_state', 'emergency_contact_lga']);
            });
        }
    }
};
