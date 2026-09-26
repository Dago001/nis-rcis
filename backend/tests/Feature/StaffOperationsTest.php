<?php

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\CardStockBatch;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Notifications\ApplicationAssigned;
use App\Support\WorkingDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('checks applicants in by slip barcode, handles walk-ins and shows only ticket numbers publicly', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $li = Application::where('status', ApplicationStatus::ApprovedForBiometrics)->firstOrFail();
    $pending = Application::where('status', ApplicationStatus::PendingApproval)->firstOrFail();
    asStaff(User::where('role', StaffRole::IssuingOfficer)->firstOrFail());

    $this->postJson('/api/v1/staff/queue/check-in', ['code' => 'NOPE'])->assertUnprocessable();
    $this->postJson('/api/v1/staff/queue/check-in', ['code' => $pending->application_number])->assertUnprocessable();

    // The appointment slip barcode (reference number), with or without dashes, or the QR text.
    $this->postJson('/api/v1/staff/queue/check-in', ['code' => str_replace('-', '', $li->reference_number)])
        ->assertCreated()->assertJsonPath('data.ticket_number', 'A001')->assertJsonPath('data.purpose', 'Biometrics capture');
    $this->postJson('/api/v1/staff/queue/check-in', ['code' => "NIS-RCIS|APP:{$li->application_number}|REF:x"])
        ->assertOk()->assertJsonPath('existing', true);
    $this->postJson('/api/v1/staff/queue/walk-in', ['name' => 'Ade Walker', 'purpose' => 'Enquiry'])
        ->assertCreated()->assertJsonPath('data.ticket_number', 'W001');

    $this->postJson('/api/v1/staff/queue/call', ['desk' => 'Desk 2'])->assertOk()->assertJsonPath('data.ticket_number', 'A001');
    $display = $this->getJson("/api/v1/public/enrollment-centers/{$li->enrollment_center_id}/queue")->assertOk()
        ->assertJsonPath('serving.0.ticket_number', 'A001')->assertJsonPath('serving.0.desk', 'Desk 2')->assertJsonPath('waiting', 1);
    expect($display->getContent())->not->toContain($li->surname)->not->toContain('WALKER');

    $id = $this->getJson('/api/v1/staff/queue')->assertOk()->assertJsonPath('stats.waiting', 1)->json('data.0.id');
    $this->postJson("/api/v1/staff/queue/{$id}/finish", ['status' => 'DONE'])->assertOk();
    $this->postJson("/api/v1/staff/queue/{$id}/finish", ['status' => 'NO_SHOW'])->assertUnprocessable();

    asStaff(User::factory()->role(StaffRole::Auditor)->create());
    $this->getJson('/api/v1/staff/queue')->assertForbidden();
});

it('records every blank card printed or spoiled against the stock', function () {
    $issuer = User::factory()->role(StaffRole::IssuingOfficer)->create();
    $card = ResidenceCard::factory()->issued()->create();
    asStaff($issuer);

    $this->postJson("/api/v1/staff/cards/{$card->id}/print-jobs", ['outcome' => 'PRINTED'])->assertUnprocessable()->assertJsonValidationErrors('stock');

    $this->postJson('/api/v1/staff/card-stock/batches', ['batch_number' => 'B-1', 'quantity' => 2, 'received_on' => today()->toDateString()])->assertCreated();
    $this->postJson("/api/v1/staff/cards/{$card->id}/print-jobs", ['outcome' => 'SPOILED'])->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/staff/cards/{$card->id}/print-jobs", ['outcome' => 'SPOILED', 'reason' => 'Ribbon jam'])->assertCreated();
    $this->getJson('/api/v1/staff/cards?unprinted=1')->assertJsonPath('data.0.id', $card->id);
    $this->postJson("/api/v1/staff/cards/{$card->id}/print-jobs", ['outcome' => 'PRINTED'])->assertCreated()->assertJsonPath('stock.remaining', 0);
    $this->getJson('/api/v1/staff/cards?unprinted=1')->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/staff/cards/{$card->id}/print-jobs", ['outcome' => 'PRINTED'])->assertUnprocessable();

    $this->getJson('/api/v1/staff/card-stock')->assertOk()
        ->assertJsonPath('stock.printed', 1)->assertJsonPath('stock.spoiled', 1)->assertJsonPath('batches.0.remaining', 0);
    $this->getJson("/api/v1/staff/cards/print-batch?ids={$card->id}")->assertOk()->assertJsonCount(1, 'data');
    expect(CardStockBatch::count())->toBe(1);
});

it('counts working days and flags applications waiting beyond the service-level target', function () {
    $friday = CarbonImmutable::parse('2026-09-25 10:00');
    expect(WorkingDays::between($friday, CarbonImmutable::parse('2026-09-28 09:00')))->toBe(1) // Fri -> Mon
        ->and(WorkingDays::between($friday, CarbonImmutable::parse('2026-10-09')))->toBe(10)
        ->and(WorkingDays::cutoff(10, CarbonImmutable::parse('2026-10-09'))->toDateString())->toBe('2026-09-25');

    $this->artisan('nis:demo')->assertSuccessful();
    $old = Application::where('status', ApplicationStatus::PendingApproval)->firstOrFail();
    $old->forceFill(['submitted_at' => now()->subWeeks(4)])->save();

    asStaff(User::where('role', StaffRole::ApprovingOfficer)->firstOrFail());
    $this->getJson('/api/v1/staff/applications?overdue=1')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sla.overdue', true);
    $this->getJson('/api/v1/staff/dashboard')->assertOk()->assertJsonPath('sla.overdue', 1);
});

it('keeps internal notes from applicants and assigns applications to officers', function () {
    Notification::fake();
    $this->artisan('nis:demo')->assertSuccessful();
    $application = Application::where('status', ApplicationStatus::PendingApproval)->firstOrFail();
    $approver = User::where('role', StaffRole::ApprovingOfficer)->firstOrFail();
    $issuer = User::where('role', StaffRole::IssuingOfficer)->firstOrFail();

    asStaff($approver);
    $this->postJson("/api/v1/staff/applications/{$application->id}/notes", ['body' => 'Called the employer: quota confirmed.'])->assertCreated();
    $this->getJson("/api/v1/staff/applications/{$application->id}/notes")->assertOk()->assertJsonPath('data.0.body', 'Called the employer: quota confirmed.');

    $auditor = User::factory()->role(StaffRole::Auditor)->create();
    $this->postJson("/api/v1/staff/applications/{$application->id}/assign", ['user_id' => $auditor->id])->assertStatus(422);
    $this->postJson("/api/v1/staff/applications/{$application->id}/assign", ['user_id' => $issuer->id])->assertOk();
    Notification::assertSentTo($issuer, ApplicationAssigned::class);

    asStaff($issuer);
    $this->getJson('/api/v1/staff/applications?assigned=me')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.assigned_to.id', $issuer->id);

    asApplicant($application->applicant);
    $response = $this->getJson("/api/v1/applicant/applications/{$application->id}")->assertOk()->assertJsonMissingPath('data.assigned_to');
    expect($response->getContent())->not->toContain('quota confirmed');
});

it('produces the monthly management report as PDF and Excel, and saves report filters', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    asStaff(User::where('role', StaffRole::SuperAdmin)->firstOrFail());

    $pdf = $this->get('/api/v1/staff/reports/management?month='.now()->format('Y-m'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    $xlsx = $this->get('/api/v1/staff/reports/management?format=xlsx&from=2026-01-01&to='.now()->toDateString())->assertOk();
    expect(substr($xlsx->streamedContent(), 0, 2))->toBe('PK');
    $cards = $this->get('/api/v1/staff/reports/export?format=xlsx&from=2026-01-01&status=ISSUED')->assertOk();
    expect(substr($cards->streamedContent(), 0, 2))->toBe('PK');

    $this->postJson('/api/v1/staff/reports/filters', ['name' => 'Ghana issued', 'filters' => ['status' => 'ISSUED', 'nationality' => 'GHANA']])->assertCreated();
    $id = $this->getJson('/api/v1/staff/reports/filters')->assertJsonPath('data.0.filters.nationality', 'GHANA')->json('data.0.id');
    $this->deleteJson("/api/v1/staff/reports/filters/{$id}")->assertNoContent();

    asStaff(User::where('role', StaffRole::IssuingOfficer)->firstOrFail());
    $this->get('/api/v1/staff/reports/management')->assertForbidden();
});
