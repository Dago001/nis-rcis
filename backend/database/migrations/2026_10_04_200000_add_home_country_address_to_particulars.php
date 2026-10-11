<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'residence_cards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'home_country_address')) {
                    $table->text('home_country_address')->nullable()->after('profession');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['applications', 'residence_cards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'home_country_address')) {
                    $table->dropColumn('home_country_address');
                }
            });
        }
    }
};
