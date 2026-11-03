<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations to remove 'demo' / 'demo.' from all usernames and update emails.
     */
    public function up(): void
    {
        $hash = Hash::make('Password123');

        // Explicit mappings for staff accounts
        $mappings = [
            'demo.admin' => 'admin',
            'demo.approver' => 'approver',
            'demo.issuer' => 'issuer',
            'demo.inspector' => 'inspector',
            'demo.auditor' => 'auditor',
        ];

        foreach ($mappings as $old => $new) {
            DB::table('users')
                ->where('username', $old)
                ->update([
                    'username' => $new,
                    'email' => "{$new}@example.com",
                    'password' => $hash,
                    'must_change_password' => false,
                ]);
        }

        // Catch any remaining username with prefix demo. or demo_
        $remaining = DB::table('users')->where('username', 'LIKE', 'demo%')->get();
        foreach ($remaining as $u) {
            $cleaned = preg_replace('/^demo[._]?/i', '', $u->username);
            if ($cleaned !== '' && ! DB::table('users')->where('username', $cleaned)->where('id', '!=', $u->id)->exists()) {
                DB::table('users')->where('id', $u->id)->update([
                    'username' => $cleaned,
                    'password' => $hash,
                    'must_change_password' => false,
                ]);
            }
        }

        // Ensure all applicants also have password Password123
        DB::table('applicants')->update([
            'password' => $hash,
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
