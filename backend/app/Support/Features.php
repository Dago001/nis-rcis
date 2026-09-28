<?php

namespace App\Support;

/**
 * Development conveniences that must never be active in production.
 */
class Features
{
    public static function skipEmailVerification(): bool
    {
        return config('nis.skip_email_verification') && ! app()->isProduction();
    }
}
