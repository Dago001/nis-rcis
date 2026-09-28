<?php

namespace App\Services;

use App\Integrations\CheckResult;
use App\Integrations\PassportRegistry;
use App\Integrations\QuotaRegistry;
use App\Models\Application;
use App\Models\IntegrationCheck;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;

/**
 * Runs the checks against other government systems for an application and
 * keeps every answer. A hit becomes a risk flag for the officer; nothing
 * here approves or rejects an application on its own.
 */
class IntegrationChecks
{
    public function __construct(
        private readonly PassportRegistry $passports,
        private readonly QuotaRegistry $quotas,
    ) {}

    /** @return Collection<int, IntegrationCheck> the checks just made */
    public function run(Application $application, ?User $officer = null): Collection
    {
        $made = collect([
            $this->record($application, IntegrationCheck::INTERPOL_SLTD, $officer,
                $this->passports->check($application->passport_number, $application->nationality)),
        ]);

        if (filled($application->quota_reference)) {
            $made->push($this->record($application, IntegrationCheck::MOI_QUOTA, $officer,
                $this->quotas->check($application->quota_reference, $application->employer_name, $application->passport_number, (string) $application->profession)));
        }

        return $made;
    }

    /** The newest check of each service for an application. */
    public static function latest(Application $application): Collection
    {
        return IntegrationCheck::with('checker:id,fullname')->where('application_id', $application->id)
            ->latest('id')->get()->unique('service')->values();
    }

    private function record(Application $application, string $service, ?User $officer, CheckResult $result): IntegrationCheck
    {
        $check = IntegrationCheck::create([
            'application_id' => $application->id,
            'service' => $service,
            'status' => $result->status,
            'reference' => $result->reference,
            'summary' => mb_substr($result->summary, 0, 255),
            'details' => $result->details ?: null,
            'checked_by' => $officer?->id,
        ]);
        Audit::log('INTEGRATION_CHECK', "{$service} for {$application->application_number}: {$result->status}", $application,
            ['reference' => $result->reference], $officer, $officer ? null : 'SYSTEM');

        return $check;
    }
}
