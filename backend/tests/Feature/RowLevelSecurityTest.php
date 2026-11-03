<?php

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\CardDistribution;
use App\Models\EnrollmentCenter;
use App\Models\NisCommand;
use App\Models\NisZone;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use App\Models\User;

beforeEach(function () {
    // Seed basic demo data
    $this->artisan('nis:demo')->assertSuccessful();
});

test('1. An applicant cannot view another applicants application or documents', function () {
    $applicant1 = Applicant::factory()->create();
    $applicant2 = Applicant::factory()->create();

    $app1 = Application::factory()->create(['applicant_id' => $applicant1->id]);
    $app2 = Application::factory()->create(['applicant_id' => $applicant2->id]);

    asApplicant($applicant1);

    // Applicant 1 can view own application
    $this->getJson("/api/v1/applicant/applications/{$app1->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $app1->id);

    // Applicant 1 CANNOT view Applicant 2's application (404 Not Found via ownership scope)
    $this->getJson("/api/v1/applicant/applications/{$app2->id}")
        ->assertNotFound();

    // Applicant 1 CANNOT view Applicant 2's slip
    $this->getJson("/api/v1/applicant/applications/{$app2->id}/slip")
        ->assertNotFound();
});

test('2. A Vetting Officer cannot retrieve unauthorised applications outside jurisdiction', function () {
    $zoneA = NisZone::where('code', 'ZONE_A')->first();
    $zoneB = NisZone::where('code', 'ZONE_B')->first();
    $commandA = NisCommand::where('zone_id', $zoneA?->id)->first();
    $commandB = NisCommand::where('zone_id', $zoneB?->id)->first();

    $centerA = EnrollmentCenter::where('command_id', $commandA?->id)->first() ?? EnrollmentCenter::first();
    $centerB = EnrollmentCenter::where('command_id', $commandB?->id)->first() ?? EnrollmentCenter::orderByDesc('id')->first();

    $vettingOfficerA = User::factory()->create([
        'role' => StaffRole::VettingOfficer,
        'command_id' => $commandA?->id,
        'zone_id' => $zoneA?->id,
    ]);

    $appA = Application::factory()->create([
        'enrollment_center_id' => $centerA->id,
        'status' => ApplicationStatus::PendingApproval,
    ]);
    $appB = Application::factory()->create([
        'enrollment_center_id' => $centerB->id,
        'status' => ApplicationStatus::PendingApproval,
    ]);

    asStaff($vettingOfficerA);

    // Officer A can view application in their jurisdiction
    $this->getJson("/api/v1/staff/applications/{$appA->id}")
        ->assertOk();

    // Officer A CANNOT view application in command B (403 Forbidden or 404 via scope)
    $res = $this->getJson("/api/v1/staff/applications/{$appB->id}");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
});

test('3. An Approving Officer cannot perform unauthorised Vetting actions', function () {
    $approver = User::where('role', StaffRole::ApprovingOfficer)->firstOrFail();
    $pending = Application::where('status', ApplicationStatus::PendingApproval)->firstOrFail();

    asStaff($approver);

    // Approving officer attempting VET action must be rejected with 403
    $this->postJson("/api/v1/staff/applications/{$pending->id}/decision", [
        'decision' => 'VET',
        'notes' => 'Attempting unauthorised vetting action',
    ])->assertForbidden();
});

test('4. A Command user cannot access another Commands restricted records', function () {
    $commandA = NisCommand::first();
    $commandB = NisCommand::where('id', '!=', $commandA->id)->first();

    $centerA = EnrollmentCenter::where('command_id', $commandA->id)->first() ?? EnrollmentCenter::first();
    $centerB = EnrollmentCenter::where('command_id', $commandB->id)->first() ?? EnrollmentCenter::orderByDesc('id')->first();

    $officerA = User::factory()->create([
        'role' => StaffRole::CommandAdmin,
        'command_id' => $commandA->id,
        'zone_id' => $commandA->zone_id,
    ]);

    $appB = Application::factory()->create(['enrollment_center_id' => $centerB->id]);

    asStaff($officerA);

    // Notes lookup on app B
    $res = $this->getJson("/api/v1/staff/applications/{$appB->id}/notes");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Adding note on app B
    $res = $this->postJson("/api/v1/staff/applications/{$appB->id}/notes", ['body' => 'Test note']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
});

test('5. A Zonal user cannot access unrelated Zones', function () {
    $zoneA = NisZone::where('code', 'ZONE_A')->first();
    $zoneB = NisZone::where('code', 'ZONE_B')->first();

    $centerB = EnrollmentCenter::where('zone_id', $zoneB?->id)->first() ?? EnrollmentCenter::orderByDesc('id')->first();

    $zonalAdminA = User::factory()->create([
        'role' => StaffRole::ZonalAdmin,
        'zone_id' => $zoneA?->id,
        'command_id' => null,
    ]);

    $appB = Application::factory()->create(['enrollment_center_id' => $centerB->id]);

    asStaff($zonalAdminA);

    $res = $this->getJson("/api/v1/staff/applications/{$appB->id}");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
});

test('6. A Card Issuance Officer cannot issue a card belonging to another location', function () {
    $commandA = NisCommand::first();
    $commandB = NisCommand::where('id', '!=', $commandA->id)->first();

    $centerB = EnrollmentCenter::where('command_id', $commandB->id)->first() ?? EnrollmentCenter::orderByDesc('id')->first();

    $cardB = ResidenceCard::factory()->create([
        'status' => CardStatus::Approved,
        'actual_enrollment_center_id' => $centerB->id,
        'enrollment_command' => $commandB->name,
    ]);

    $appB = Application::factory()->create([
        'card_id' => $cardB->id,
        'status' => ApplicationStatus::Approved,
        'enrollment_center_id' => $centerB->id,
    ]);

    $issuanceOfficerA = User::factory()->create([
        'role' => StaffRole::CardIssuanceOfficer,
        'command_id' => $commandA->id,
        'zone_id' => $commandA->zone_id,
    ]);

    asStaff($issuanceOfficerA);

    // Issuing officer at Command A attempting to issue a card from Command B
    $this->postJson("/api/v1/staff/card-issuance/{$cardB->id}/issue-out")
        ->assertForbidden();
});

test('7. Dashboard totals respect user permissions', function () {
    $commandA = NisCommand::first();
    $officerA = User::factory()->create([
        'role' => StaffRole::CommandAdmin,
        'command_id' => $commandA->id,
        'zone_id' => $commandA->zone_id,
    ]);

    asStaff($officerA);

    $res = $this->getJson('/api/v1/staff/dashboard')->assertOk();
    $json = $res->json();

    expect($json)->toHaveKey('queue');
    expect($json)->toHaveKey('cards');
    expect($json)->toHaveKey('sla');
    // Non-headquarters users do not get unvetted nationwide counts
    expect($json['paid_awaiting_submission'])->toBe(0);
});

test('8. Direct API requests cannot bypass record restrictions via tampered IDs', function () {
    $zoneB = NisZone::where('code', 'ZONE_B')->first();
    $centerB = EnrollmentCenter::where('zone_id', $zoneB?->id)->first() ?? EnrollmentCenter::orderByDesc('id')->first();

    $appB = Application::factory()->create(['enrollment_center_id' => $centerB->id]);

    $commandA = NisCommand::first();
    $officerA = User::factory()->create([
        'role' => StaffRole::VettingOfficer,
        'command_id' => $commandA->id,
        'zone_id' => $commandA->zone_id,
    ]);

    asStaff($officerA);

    // Direct slip tampering
    $res = $this->getJson("/api/v1/staff/applications/{$appB->id}/slip");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Direct temporary card tampering
    $res = $this->getJson("/api/v1/staff/applications/{$appB->id}/temporary-card");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Direct risk check tampering
    $res = $this->postJson("/api/v1/staff/applications/{$appB->id}/risk-check");
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
});

test('9. Database queries and scopes do not unintentionally expose protected data', function () {
    $applicant = Applicant::factory()->create();
    $app1 = Application::factory()->create(['applicant_id' => $applicant->id]);
    $appOther = Application::factory()->create();

    asApplicant($applicant);

    // Eloquent Application::all() under applicant context must only return their own application
    $apps = Application::all();
    expect($apps->pluck('id')->contains($app1->id))->toBeTrue();
    expect($apps->pluck('id')->contains($appOther->id))->toBeFalse();
});

test('10. Existing authorised workflows continue functioning seamlessly', function () {
    $hqAdmin = User::where('role', StaffRole::SuperAdmin)->firstOrFail();
    asStaff($hqAdmin);

    // Headquarters admin can view applications
    $this->getJson('/api/v1/staff/applications')->assertOk();

    // Headquarters admin can view dashboard
    $this->getJson('/api/v1/staff/dashboard')->assertOk();

    // Queue index works for authorized centers
    $this->getJson('/api/v1/staff/queue')->assertOk();
});
