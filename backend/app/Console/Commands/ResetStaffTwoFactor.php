<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Server-side recovery when no other Super Administrator can reset an
 * officer's authenticator (e.g. the only Super Administrator lost their phone).
 */
class ResetStaffTwoFactor extends Command
{
    protected $signature = 'nis:staff-reset-2fa {service_number : Service number or username}';

    protected $description = "Reset a staff member's authenticator app so they set it up again at next sign-in";

    public function handle(): int
    {
        $id = (string) $this->argument('service_number');
        $user = User::where('service_number', $id)->orWhere('username', $id)->first();
        if (! $user) {
            $this->error('No staff account with that service number or username.');

            return self::FAILURE;
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $user->tokens()->update(['revoked' => true]);
        Audit::log('STAFF_2FA_RESET', "Authenticator reset from the server console for {$user->auditLabel()}", $user, actorLabel: 'SYSTEM');
        $this->info("Authenticator reset for {$user->auditLabel()}.");

        return self::SUCCESS;
    }
}
