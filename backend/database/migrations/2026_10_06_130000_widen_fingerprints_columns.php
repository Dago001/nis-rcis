<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->longText('fingerprints')->nullable()->change();
            $table->longText('fingerprint_template')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('fingerprints')->nullable()->change();
            $table->text('fingerprint_template')->nullable()->change();
        });
    }
};
