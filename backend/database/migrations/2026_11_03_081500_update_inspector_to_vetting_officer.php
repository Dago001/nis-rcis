<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations to update staff user 10004 to Vetting Officer.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('service_number', '10004')
            ->orWhereIn('username', ['inspector', 'demo.inspector'])
            ->update([
                'username' => 'vetting',
                'email' => 'vetting@example.com',
                'role' => 'VettingOfficer',
                'password' => Hash::make('Password123'),
                'must_change_password' => false,
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
        // Reversal not required
    }
};
