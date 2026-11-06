<?php

use App\Enums\StaffRole;
use App\Models\NisCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces explicit command_id and zone_id foreign keys for staff users,
     * links Alagbon Office officers strictly to Lagos State Command (LAG-SC),
     * and provisions a dedicated Command Administrator account for Lagos State Command.
     */
    public function up(): void
    {
        // 1. Locate Lagos State Command (LAG-SC)
        $lagosCommand = DB::table('nis_commands')->where('code', 'LAG-SC')->first();

        if ($lagosCommand) {
            // Update all operational staff with Alagbon Office to LAG-SC
            DB::table('users')
                ->where(function ($q) {
                    $q->where('command', 'LIKE', '%Alagbon%')
                      ->orWhere('command', 'LIKE', '%Ikoyi%');
                })
                ->where('role', '!=', StaffRole::SuperAdmin->value)
                ->update([
                    'command_id' => $lagosCommand->id,
                    'zone_id' => $lagosCommand->zone_id,
                    'state' => 'Lagos',
                ]);
        }

        // 2. Safely backfill any other staff users where command_id is null by matching command name
        try {
            $commands = DB::table('nis_commands')->get();
            foreach ($commands as $cmd) {
                DB::table('users')
                    ->whereNull('command_id')
                    ->where('role', '!=', StaffRole::SuperAdmin->value)
                    ->where('command', $cmd->name)
                    ->update([
                        'command_id' => $cmd->id,
                        'zone_id' => $cmd->zone_id,
                        'state' => $cmd->state,
                    ]);
            }
        } catch (\Throwable $e) {
            // Ignore if empty
        }

        // 3. Ensure Super Administrator (admin / Ibrahim Musa) has nationwide scope (null command_id & zone_id)
        DB::table('users')
            ->where('role', StaffRole::SuperAdmin->value)
            ->update([
                'command_id' => null,
                'zone_id' => null,
                'command' => 'Headquarters (Sauka Abuja) / Nationwide',
            ]);

        // 4. Provision or verify dedicated Command Administrator for Lagos State Command
        if ($lagosCommand) {
            $existingAdmin = DB::table('users')
                ->where('command_id', $lagosCommand->id)
                ->where('role', StaffRole::CommandAdmin->value)
                ->first();

            if (! $existingAdmin) {
                // Check if username cmdadmin.lagos exists
                $adminUser = DB::table('users')->where('username', 'cmdadmin.lagos')->first();
                if ($adminUser) {
                    DB::table('users')->where('id', $adminUser->id)->update([
                        'role' => StaffRole::CommandAdmin->value,
                        'command_id' => $lagosCommand->id,
                        'zone_id' => $lagosCommand->zone_id,
                        'command' => $lagosCommand->name,
                        'state' => 'Lagos',
                        'is_active' => true,
                    ]);
                } else {
                    DB::table('users')->insert([
                        'username' => 'cmdadmin.lagos',
                        'fullname' => 'Babatunde Alabi',
                        'rank' => 'CIS',
                        'service_number' => 'NIS-10006',
                        'email' => 'cmdadmin.lagos@example.com',
                        'password' => Hash::make('Password123'),
                        'role' => StaffRole::CommandAdmin->value,
                        'command' => $lagosCommand->name,
                        'command_id' => $lagosCommand->id,
                        'zone_id' => $lagosCommand->zone_id,
                        'state' => 'Lagos',
                        'is_active' => true,
                        'must_change_password' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
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
