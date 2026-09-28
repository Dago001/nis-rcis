<?php

namespace App\Integrations;

/** Ministry of Interior expatriate quota register. */
interface QuotaRegistry
{
    public function check(string $quotaReference, ?string $employer, string $passportNumber, string $profession): CheckResult;
}
