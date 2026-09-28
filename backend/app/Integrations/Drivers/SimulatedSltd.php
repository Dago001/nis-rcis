<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use App\Integrations\PassportRegistry;
use Illuminate\Support\Str;

/**
 * Test stand-in until the Interpol connection exists (never used in
 * production): a passport number beginning with "SLTD", or listed in
 * INTERPOL_SLTD_TEST_HITS, is a hit.
 */
class SimulatedSltd implements PassportRegistry
{
    public function check(string $passportNumber, string $nationality): CheckResult
    {
        $hits = array_map('strtoupper', config('nis.integrations.interpol_sltd.test_hits', []));
        $reference = 'SIM-SLTD-'.strtoupper(Str::random(8));

        return str_starts_with($passportNumber, 'SLTD') || in_array($passportNumber, $hits, true)
            ? new CheckResult(CheckResult::HIT, 'Interpol SLTD (simulated): this passport is recorded as stolen.', $reference, ['records' => [['status' => 'STOLEN', 'reporting_country' => $nationality]]])
            : new CheckResult(CheckResult::CLEAR, 'Interpol SLTD (simulated): no record for this passport.', $reference);
    }
}
