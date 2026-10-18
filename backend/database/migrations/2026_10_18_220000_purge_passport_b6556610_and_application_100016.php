<?php

use App\Services\PassportDataPurger;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Completely remove passport B6556610 and application RC-2026-100016 and all related data.
     */
    public function up(): void
    {
        PassportDataPurger::purge('B6556610', 'RC-2026-100016');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Purge operation is irreversible.
    }
};
