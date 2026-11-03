<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations to reset all staff and applicant passwords to Password123.
     */
    public function up(): void
    {
        $hash = Hash::make('Password123');

        // Update all staff accounts
        DB::table('users')->update([
            'password' => $hash,
            'must_change_password' => false,
        ]);

        // Update all applicant accounts
        DB::table('applicants')->update([
            'password' => $hash,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Passwords are not reverted
    }
};
