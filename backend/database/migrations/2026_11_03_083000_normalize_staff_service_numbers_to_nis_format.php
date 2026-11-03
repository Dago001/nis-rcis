<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to normalize all staff service numbers to NIS-XXXXX format.
     */
    public function up(): void
    {
        $users = DB::table('users')->whereNotNull('service_number')->get();
        foreach ($users as $user) {
            $raw = trim((string) $user->service_number);
            $clean = preg_replace('/[^0-9]/', '', $raw);
            if ($clean !== '') {
                $nisFormatted = 'NIS-' . $clean;
                if ($raw !== $nisFormatted) {
                    DB::table('users')->where('id', $user->id)->update([
                        'service_number' => $nisFormatted,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversal not required
    }
};
