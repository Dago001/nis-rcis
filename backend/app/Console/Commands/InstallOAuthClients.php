<?php

namespace App\Console\Commands;

use App\Support\OAuthClients;
use Illuminate\Console\Command;

class InstallOAuthClients extends Command
{
    protected $signature = 'nis:oauth-clients
        {--frontend= : Public URL of the Next.js frontend (defaults to FRONTEND_URL)}';

    protected $description = 'Create the first-party OAuth2 clients for the staff console and applicant portal';

    public function handle(): int
    {
        $frontend = rtrim($this->option('frontend') ?: config('nis.frontend_url'), '/');

        [$staff, $staffSecret] = OAuthClients::staffConsole("{$frontend}/api/auth/callback/staff");
        [$portal, $portalSecret] = OAuthClients::applicantPortal("{$frontend}/api/auth/callback/applicant");

        $this->info('OAuth2 clients created. Put these in frontend/.env.local — secrets are shown only once.');
        $this->newLine();
        $this->line("OAUTH_STAFF_CLIENT_ID={$staff->getKey()}");
        $this->line("OAUTH_STAFF_CLIENT_SECRET={$staffSecret}");
        $this->line("OAUTH_APPLICANT_CLIENT_ID={$portal->getKey()}");
        $this->line("OAUTH_APPLICANT_CLIENT_SECRET={$portalSecret}");

        return self::SUCCESS;
    }
}
