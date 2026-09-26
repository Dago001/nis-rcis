<?php

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Integrations\CheckResult;
use App\Integrations\Drivers\InterpolSltdHttp;
use App\Integrations\Drivers\NotConfigured;
use App\Integrations\PassportRegistry;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\IntegrationCheck;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('checks the passport with Interpol SLTD and the quota with the Ministry of Interior on submission', function () {
    Notification::fake();
    asApplicant(Applicant::factory()->create(['email' => 'jp@example.com']));
    uploadDraftDocs();

    $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => paidReference(), 'quota_reference' => 'moi/eq/2026/77']))
        ->assertUnprocessable()->assertJsonValidationErrors('employer_name');

    $id = $this->postJson('/api/v1/applicant/applications', particulars([
        'payment_reference' => paidReference(), 'passport_number' => 'SLTD12345',
        'quota_reference' => 'moi/eq/2026/77', 'employer_name' => 'Dangote Cement',
    ]))->assertCreated()->assertJsonPath('data.quota_reference', 'MOI/EQ/2026/77')->json('data.id');

    $checks = IntegrationCheck::where('application_id', $id)->pluck('status', 'service');
    expect($checks[IntegrationCheck::INTERPOL_SLTD])->toBe(CheckResult::HIT)
        ->and($checks[IntegrationCheck::MOI_QUOTA])->toBe(CheckResult::VALID);

    asStaff(User::factory()->role(StaffRole::ApprovingOfficer)->create());
    $response = $this->getJson("/api/v1/staff/applications/{$id}")->assertOk();
    expect(collect($response->json('data.risk_flags'))->pluck('code'))->toContain('INTERPOL_SLTD_HIT')
        ->and(collect($response->json('integration_checks'))->pluck('status', 'service')->all())
        ->toMatchArray(['INTERPOL_SLTD' => 'HIT', 'MOI_QUOTA' => 'VALID']);
});

it('records a connection that is down or not configured, and officers can re-run the checks', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $application = Application::where('status', ApplicationStatus::PendingApproval)->firstOrFail();
    $application->forceFill(['quota_reference' => 'MOI/EQ/2026/000', 'employer_name' => 'ACME'])->save();

    config(['nis.integrations.interpol_sltd' => ['driver' => 'http', 'url' => 'https://sltd.test', 'api_key' => 'k', 'timeout' => 2]]);
    // Down for the first check (two attempts), answering for the second.
    Http::fake(['sltd.test/*' => Http::sequence()->push(['message' => 'down'], 503)->push(['message' => 'down'], 503)->push(['hit' => false, 'reference' => 'NCB-1'])]);

    asStaff(User::where('role', StaffRole::ApprovingOfficer)->firstOrFail());
    $data = $this->postJson("/api/v1/staff/applications/{$application->id}/integration-checks")->assertOk()->json('data');
    expect(collect($data)->pluck('status', 'service')->all())->toMatchArray(['INTERPOL_SLTD' => 'ERROR', 'MOI_QUOTA' => 'NOT_FOUND']);
    Http::assertSent(fn ($r) => $r->url() === 'https://sltd.test/sltd/search' && $r['document_number'] === $application->passport_number && $r->hasHeader('Authorization', 'Bearer k'));

    $sltd = collect($this->postJson("/api/v1/staff/applications/{$application->id}/integration-checks")->assertOk()->json('data'))
        ->firstWhere('service', 'INTERPOL_SLTD');
    expect($sltd)->toMatchArray(['status' => 'CLEAR', 'reference' => 'NCB-1']);

    asStaff(User::where('role', StaffRole::IssuingOfficer)->firstOrFail());
    $this->postJson("/api/v1/staff/applications/{$application->id}/integration-checks")->assertForbidden();
});

it('never uses simulated answers on the live system', function () {
    app()->detectEnvironment(fn () => 'production');
    expect(app(PassportRegistry::class))->toBeInstanceOf(NotConfigured::class);

    config(['nis.integrations.interpol_sltd.driver' => 'http', 'nis.integrations.interpol_sltd.url' => 'https://sltd.example']);
    expect(app(PassportRegistry::class))->toBeInstanceOf(InterpolSltdHttp::class);
});

it('stores fingerprint templates from the scanner encrypted and never returns them', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $application = Application::where('status', ApplicationStatus::ApprovedForBiometrics)->firstOrFail();
    asStaff(User::where('role', StaffRole::IssuingOfficer)->firstOrFail());

    $template = base64_encode(random_bytes(400));
    $body = ['photo' => $this->pngDataUrl(), 'signature' => $this->pngDataUrl(), 'fingerprints' => [
        ['finger' => 'R_INDEX', 'template' => $template, 'format' => 'ISO_19794_2', 'quality' => 82, 'nfiq' => 2, 'device' => 'SecuGen Hamster Pro 20'],
        ['finger' => 'R_INDEX', 'template' => $template, 'format' => 'ISO_19794_2'],
    ]];
    $this->postJson("/api/v1/staff/applications/{$application->id}/biometrics", $body)->assertUnprocessable()->assertJsonValidationErrors('fingerprints.1.finger');

    $body['fingerprints'][1]['finger'] = 'L_INDEX';
    $this->postJson("/api/v1/staff/applications/{$application->id}/biometrics", $body)->assertOk();

    $response = $this->getJson("/api/v1/staff/applications/{$application->id}")->assertOk()
        ->assertJsonPath('data.fingerprints_captured', ['R_INDEX', 'L_INDEX']);
    expect($response->getContent())->not->toContain($template)
        ->and(DB::table('applications')->where('id', $application->id)->value('fingerprints'))->not->toContain($template)
        ->and($application->fresh()->fingerprints[0]['template'])->toBe($template);
});
