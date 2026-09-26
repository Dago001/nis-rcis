<?php

use App\Enums\CardStatus;
use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDraft;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\RiskChecker;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('registration'));

it('requires consent to the privacy notice and records it', function () {
    $body = ['surname' => 'Doe', 'forenames' => 'Jane', 'email' => 'jane@example.com', 'phone' => '+2348031234567',
        'password' => 'Nis2026x', 'password_confirmation' => 'Nis2026x'];

    $this->postJson('/api/v1/applicant/register', $body)->assertUnprocessable()->assertJsonValidationErrors('privacy_consent');
    $this->postJson('/api/v1/applicant/register', [...$body, 'privacy_consent' => true])->assertAccepted();

    $applicant = Applicant::where('email', 'jane@example.com')->firstOrFail();
    expect($applicant->privacy_consent_at)->not->toBeNull()
        ->and($applicant->privacy_policy_version)->toBe(config('nis.privacy_policy_version'));
});

it('lets applicants download their own data, and only their own', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $amara = Applicant::where('email', 'amara.diallo@example.com')->firstOrFail();
    asApplicant($amara);

    $response = $this->getJson('/api/v1/applicant/my-data')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="my-nis-rcis-data.json"')
        ->assertJsonPath('account.email', 'amara.diallo@example.com');
    expect($response->json('applications'))->toHaveCount(1);
    expect($response->getContent())->not->toContain('kwame.mensah')->not->toContain('fingerprint_template');
});

it('flags duplicate passports, shared phones and revoked cards for officers only', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $john = Application::where('email', 'john.smith@example.com')->firstOrFail();
    $kwame = Application::where('email', 'kwame.mensah@example.com')->firstOrFail();

    // Kwame's application reuses John's passport number and phone.
    $kwame->forceFill(['passport_number' => $john->passport_number, 'phone' => $john->phone])->save();
    ResidenceCard::where('passport_number', $john->passport_number)->update(['is_watchlisted' => true]);

    $codes = collect(app(RiskChecker::class)->check($kwame))->pluck('code');
    expect($codes)->toContain('PASSPORT_OTHER_ACCOUNT', 'PASSPORT_PARTICULARS_MISMATCH', 'PHONE_SHARED', 'PASSPORT_CARD_REVOKED_OR_WATCHLISTED');

    asStaff(User::where('role', StaffRole::Inspector)->firstOrFail());
    $this->getJson("/api/v1/staff/applications/{$kwame->id}")->assertOk()->assertJsonPath('data.risk_flags.0.severity', 'HIGH');
    expect(count($this->postJson("/api/v1/staff/applications/{$kwame->id}/risk-check")->assertOk()->json('risk_flags')))->toBeGreaterThanOrEqual(4);

    asApplicant(Applicant::where('email', 'kwame.mensah@example.com')->firstOrFail());
    $this->getJson("/api/v1/applicant/applications/{$kwame->id}")->assertOk()->assertJsonMissingPath('data.risk_flags');
});

it('applies card edits only after a second officer approves them', function () {
    $card = ResidenceCard::factory()->create(['profession' => 'ENGINEER']);
    $card->forceFill(['status' => CardStatus::Approved])->save();
    $issuer = User::factory()->role(StaffRole::IssuingOfficer)->create();
    $approver = User::factory()->role(StaffRole::ApprovingOfficer)->create();

    asStaff($issuer);
    $this->putJson("/api/v1/staff/cards/{$card->id}", ['profession' => 'ARCHITECT'])->assertUnprocessable()->assertJsonValidationErrors('change_reason');
    $id = $this->putJson("/api/v1/staff/cards/{$card->id}", ['profession' => 'ARCHITECT', 'change_reason' => 'Typo at capture'])
        ->assertStatus(202)->json('approval_request_id');
    $this->putJson("/api/v1/staff/cards/{$card->id}", ['profession' => 'PILOT', 'change_reason' => 'x'])->assertUnprocessable();
    expect($card->fresh()->profession)->toBe('ENGINEER');

    asStaff($approver);
    $this->postJson("/api/v1/staff/approvals/{$id}/reject", [])->assertUnprocessable();
    $this->postJson("/api/v1/staff/approvals/{$id}/approve", ['notes' => 'Checked against passport'])->assertOk();
    expect($card->fresh()->profession)->toBe('ARCHITECT');
    $this->postJson("/api/v1/staff/approvals/{$id}/approve")->assertUnprocessable();
});

it('keeps a breach register with the 72-hour regulator deadline', function () {
    $admin = User::factory()->role(StaffRole::SuperAdmin)->create();
    asStaff($admin);

    $id = $this->postJson('/api/v1/staff/data-breaches', [
        'title' => 'Lost laptop', 'description' => 'Unencrypted export on a lost laptop', 'severity' => 'HIGH',
        'detected_at' => now()->subHours(80)->toIso8601String(), 'affected_count' => 12,
    ])->assertCreated()->assertJsonPath('data.regulator_overdue', true)->json('data.id');

    $this->patchJson("/api/v1/staff/data-breaches/{$id}", ['regulator_notified_at' => now()->toIso8601String(), 'status' => 'CONTAINED'])
        ->assertOk()->assertJsonPath('data.regulator_overdue', false);

    asStaff(User::factory()->role(StaffRole::Auditor)->create());
    $this->getJson('/api/v1/staff/data-breaches')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/v1/staff/data-breaches', [])->assertForbidden();
});

it('deletes abandoned drafts and unverified accounts past their retention period', function () {
    $old = Applicant::factory()->unverified()->create(['created_at' => now()->subDays(40)]);
    $recent = Applicant::factory()->unverified()->create();
    $withDraft = Applicant::factory()->create();
    ApplicationDraft::create(['applicant_id' => $withDraft->id, 'type' => 'NEW', 'current_step' => 1, 'data' => []]);
    ApplicationDraft::query()->update(['updated_at' => now()->subDays(100)]);

    $this->artisan('nis:retention')->assertSuccessful();

    expect(Applicant::find($old->id))->toBeNull()
        ->and(Applicant::find($recent->id))->not->toBeNull()
        ->and(ApplicationDraft::count())->toBe(0);
});
