<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use App\Integrations\QuotaRegistry;
use Illuminate\Support\Str;

/**
 * Test stand-in until the Ministry of Interior connection exists (never
 * used in production): numbers like "MOI/EQ/2026/123" are valid; ending in
 * "000" not found; containing "EXP" expired.
 */
class SimulatedQuota implements QuotaRegistry
{
    public function check(string $quotaReference, ?string $employer, string $passportNumber, string $profession): CheckResult
    {
        $ref = strtoupper(trim($quotaReference));
        $reference = 'SIM-MOI-'.strtoupper(Str::random(8));

        return match (true) {
            ! preg_match('#^[A-Z]{2,5}(/[A-Z0-9]{1,6}){2,4}$#', $ref), str_ends_with($ref, '000') => new CheckResult(CheckResult::NOT_FOUND, 'Ministry of Interior (simulated): no expatriate quota with this number.', $reference),
            str_contains($ref, 'EXP') => new CheckResult(CheckResult::INVALID, 'Ministry of Interior (simulated): quota not valid (expired).', $reference, ['reason' => 'expired']),
            default => new CheckResult(CheckResult::VALID, 'Ministry of Interior (simulated): expatriate quota confirmed'.($employer ? " for {$employer}." : '.'), $reference,
                ['employer' => $employer, 'positions_approved' => 5, 'positions_used' => 2, 'expires_on' => now()->addYear()->toDateString()]),
        };
    }
}
