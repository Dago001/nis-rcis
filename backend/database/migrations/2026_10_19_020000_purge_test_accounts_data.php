<?php

use App\Services\TestAccountResetter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Purge all test applications, drafts, cards, and associated records for
     * blessingeze00034@gmail.com and ezeab.nis@gmail.com without backup,
     * while preserving both login accounts.
     */
    public function up(): void
    {
        TestAccountResetter::reset();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletion of test data is irreversible.
    }
};
