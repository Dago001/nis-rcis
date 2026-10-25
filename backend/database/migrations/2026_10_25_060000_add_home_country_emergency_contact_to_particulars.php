<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_name')) {
                    $table->string('home_country_emergency_contact_name', 150)->nullable()->after('emergency_contact_lga');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_relation')) {
                    $table->string('home_country_emergency_contact_relation', 50)->nullable()->after('home_country_emergency_contact_name');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_phone')) {
                    $table->string('home_country_emergency_contact_phone', 30)->nullable()->after('home_country_emergency_contact_relation');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_address')) {
                    $table->string('home_country_emergency_contact_address', 300)->nullable()->after('home_country_emergency_contact_phone');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'home_country_emergency_contact_name',
                    'home_country_emergency_contact_relation',
                    'home_country_emergency_contact_phone',
                    'home_country_emergency_contact_address',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
