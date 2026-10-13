<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'state')) {
                    $table->string('state', 100)->nullable()->after('command');
                }
                if (! Schema::hasColumn('users', 'lga')) {
                    $table->string('lga', 100)->nullable()->after('state');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_filter(['state', 'lga'], fn ($col) => Schema::hasColumn('users', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
