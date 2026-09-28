<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use App\Integrations\PassportRegistry;
use App\Integrations\QuotaRegistry;

/** Used until a connection is configured: the check is recorded as not done. */
class NotConfigured implements PassportRegistry, QuotaRegistry
{
    public function check(string $first, ?string $second = null, ?string $third = null, ?string $fourth = null): CheckResult
    {
        return new CheckResult(CheckResult::DISABLED, 'Not checked: this connection is not configured yet. Verify manually.');
    }
}
