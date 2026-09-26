<?php

namespace App\Console\Commands;

use App\Support\Audit;
use App\Support\OAuthClients;
use Illuminate\Console\Command;

class CreatePartnerClient extends Command
{
    protected $signature = 'nis:partner-client {name : Partner agency name, e.g. "Nigeria Police Force"}';

    protected $description = 'Create an OAuth2 client_credentials client for a partner agency (cards:verify scope only)';

    public function handle(): int
    {
        [$client, $secret] = OAuthClients::partner($this->argument('name'));

        Audit::log('OAUTH_PARTNER_CLIENT_CREATED', "Partner client created: {$client->name}", $client, actorLabel: 'CLI');

        $this->info('Partner client created. Share these over a secure channel — the secret is shown only once.');
        $this->line("client_id:     {$client->getKey()}");
        $this->line("client_secret: {$secret}");
        $this->line('grant_type:    client_credentials');
        $this->line('scope:         cards:verify');
        $this->line('token URL:     '.url('/oauth/token'));

        return self::SUCCESS;
    }
}
