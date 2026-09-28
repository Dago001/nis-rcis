<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use App\Integrations\QuotaRegistry;

/**
 * Ministry of Interior expatriate quota register.
 *
 * Request:  POST {url}/quotas/verify  {"quota_reference", "employer_name", "passport_number", "position"}
 * Response: {"found": bool, "valid": bool, "reference", "employer", "positions_approved", "positions_used", "expires_on", "reason"}
 */
class MoiQuotaHttp extends HttpJsonClient implements QuotaRegistry
{
    public function check(string $quotaReference, ?string $employer, string $passportNumber, string $profession): CheckResult
    {
        return $this->post('quotas/verify', [
            'quota_reference' => $quotaReference,
            'employer_name' => $employer,
            'passport_number' => $passportNumber,
            'position' => $profession,
        ], function (array $r) {
            $details = array_intersect_key($r, array_flip(['employer', 'positions_approved', 'positions_used', 'expires_on', 'reason']));

            return match (true) {
                empty($r['found']) => new CheckResult(CheckResult::NOT_FOUND, 'Ministry of Interior: no expatriate quota with this number.', $r['reference'] ?? null),
                empty($r['valid']) => new CheckResult(CheckResult::INVALID, 'Ministry of Interior: quota not valid'.(isset($r['reason']) ? " ({$r['reason']})." : '.'), $r['reference'] ?? null, $details),
                default => new CheckResult(CheckResult::VALID, 'Ministry of Interior: expatriate quota confirmed'.(isset($r['employer']) ? " for {$r['employer']}." : '.'), $r['reference'] ?? null, $details),
            };
        });
    }
}
