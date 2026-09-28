<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates staff accounts from the CLI (used for the first SuperAdmin).
 * There are no default passwords: a random temporary password is printed
 * once and must be changed at first sign-in.
 */
class CreateStaffUser extends Command
{
    protected $signature = 'nis:create-staff
        {--username=} {--fullname=} {--service-number=} {--email=}
        {--role=SuperAdmin : SuperAdmin|ApprovingOfficer|IssuingOfficer|Inspector|Auditor}
        {--command=National Processing Center}';

    protected $description = 'Create a staff account with a one-time temporary password';

    public function handle(): int
    {
        $role = StaffRole::tryFrom($this->option('role'));
        if (! $role) {
            $this->error('Invalid role.');

            return self::FAILURE;
        }

        $password = Str::password(16);

        $user = User::create([
            'username' => $this->option('username') ?: $this->ask('Username'),
            'fullname' => $this->option('fullname') ?: $this->ask('Full name'),
            'service_number' => $this->option('service-number') ?: $this->ask('Service number'),
            'email' => $this->option('email') ?: $this->ask('E-mail'),
            'role' => $role,
            'command' => $this->option('command'),
            'password' => $password,
            'must_change_password' => true,
        ]);

        Audit::log('STAFF_CREATED', "Staff account created from CLI with role {$role->value}", $user, actorLabel: 'CLI');

        $this->info("Created {$role->label()} {$user->fullname} ({$user->service_number}).");
        $this->line("Temporary password (shown once): {$password}");

        return self::SUCCESS;
    }
}
