<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to reset 2FA secret and confirmation for all staff,
     * ensuring that every officer is presented with the QR barcode scan on login.
     */
    public function up(): void
    {
        DB::table('users')->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action on rollback
    }
};
