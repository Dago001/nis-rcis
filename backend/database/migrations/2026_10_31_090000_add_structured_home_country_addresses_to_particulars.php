<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'home_country')) {
                    $table->string('home_country', 100)->nullable()->after('home_country_address');
                }
                if (! Schema::hasColumn($tableName, 'home_country_state')) {
                    $table->string('home_country_state', 150)->nullable()->after('home_country');
                }
                if (! Schema::hasColumn($tableName, 'home_country_city')) {
                    $table->string('home_country_city', 150)->nullable()->after('home_country_state');
                }
                if (! Schema::hasColumn($tableName, 'home_country_district')) {
                    $table->string('home_country_district', 150)->nullable()->after('home_country_city');
                }
                if (! Schema::hasColumn($tableName, 'home_country_postal_code')) {
                    $table->string('home_country_postal_code', 30)->nullable()->after('home_country_district');
                }

                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_country')) {
                    $table->string('home_country_emergency_contact_country', 100)->nullable()->after('home_country_emergency_contact_address');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_state')) {
                    $table->string('home_country_emergency_contact_state', 150)->nullable()->after('home_country_emergency_contact_country');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_city')) {
                    $table->string('home_country_emergency_contact_city', 150)->nullable()->after('home_country_emergency_contact_state');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_district')) {
                    $table->string('home_country_emergency_contact_district', 150)->nullable()->after('home_country_emergency_contact_city');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_postal_code')) {
                    $table->string('home_country_emergency_contact_postal_code', 30)->nullable()->after('home_country_emergency_contact_district');
                }
            });

            // Populate home_country from nationality for existing records if empty
            if (Schema::hasColumn($tableName, 'home_country') && Schema::hasColumn($tableName, 'nationality')) {
                DB::table($tableName)
                    ->whereNull('home_country')
                    ->whereNotNull('nationality')
                    ->update(['home_country' => DB::raw('nationality')]);
            }
        }
    }

    public function down(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'home_country',
                    'home_country_state',
                    'home_country_city',
                    'home_country_district',
                    'home_country_postal_code',
                    'home_country_emergency_contact_country',
                    'home_country_emergency_contact_state',
                    'home_country_emergency_contact_city',
                    'home_country_emergency_contact_district',
                    'home_country_emergency_contact_postal_code',
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
