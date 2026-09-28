<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use App\Integrations\PassportRegistry;

/**
 * Interpol SLTD search via the NCB Abuja gateway (I-24/7 FIND).
 *
 * Request:  POST {url}/sltd/search  {"document_number", "document_type": "PASSPORT", "issuing_country"}
 * Response: {"hit": bool, "reference": string, "records": [{"status", "reported_on", "reporting_country"}]}
 */
class InterpolSltdHttp extends HttpJsonClient implements PassportRegistry
{
    public function check(string $passportNumber, string $nationality): CheckResult
    {
        return $this->post('sltd/search', [
            'document_number' => $passportNumber,
            'document_type' => 'PASSPORT',
            'issuing_country' => $nationality,
        ], fn (array $r) => ! empty($r['hit'])
            ? new CheckResult(CheckResult::HIT, 'Interpol SLTD: this passport is recorded as stolen or lost.', $r['reference'] ?? null, ['records' => $r['records'] ?? []])
            : new CheckResult(CheckResult::CLEAR, 'Interpol SLTD: no record for this passport.', $r['reference'] ?? null));
    }
}
