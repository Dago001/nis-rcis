<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'nis:vapid-keys';

    protected $description = 'Create the key pair for push notifications (add the lines to backend/.env)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->comment('Keep the private key secret. Changing the keys signs every device out of notifications.');

        return self::SUCCESS;
    }
}
