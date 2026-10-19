<?php

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDraft;
use App\Models\ApplicationNote;
use App\Models\ApplicationStatusHistory;
use App\Models\Payment;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\TestAccountResetter;

it('resets all application data for specified test accounts while preserving login accounts and unrelated data', function () {
    // 1. Create test applicant accounts
    $applicant1 = Applicant::factory()->create([
        'email' => 'ezeab.nis@gmail.com',
        'passport_number' => 'TESTPASS001',
    ]);

    $applicant2 = Applicant::factory()->create([
        'email' => 'blessingeze00034@gmail.com',
        'passport_number' => 'TESTPASS002',
    ]);

    // Unrelated applicant (must NOT be touched)
    $unrelatedApplicant = Applicant::factory()->create([
        'email' => 'unrelated.applicant@example.com',
        'passport_number' => 'UNRELATED01',
    ]);

    // 2. Create principal application for applicant 1
    $principalApp = Application::factory()->create([
        'applicant_id' => $applicant1->id,
        'email' => 'ezeab.nis@gmail.com',
        'application_number' => 'RC-2026-999001',
        'passport_number' => 'TESTPASS001',
        'status' => ApplicationStatus::PendingApproval,
    ]);

    // Create dependent application linked to principal
    $dependentApp = Application::factory()->create([
        'applicant_id' => $applicant1->id,
        'email' => 'ezeab.nis@gmail.com',
        'principal_application_id' => $principalApp->id,
        'application_number' => 'RC-2026-999002',
        'passport_number' => 'DEP-PASS-001',
        'status' => ApplicationStatus::PendingApproval,
    ]);

    // Create application for applicant 2
    $app2 = Application::factory()->create([
        'applicant_id' => $applicant2->id,
        'email' => 'blessingeze00034@gmail.com',
        'application_number' => 'RC-2026-999003',
        'passport_number' => 'TESTPASS002',
        'status' => ApplicationStatus::PendingApproval,
    ]);

    // Unrelated application
    $unrelatedApp = Application::factory()->create([
        'applicant_id' => $unrelatedApplicant->id,
        'email' => 'unrelated.applicant@example.com',
        'application_number' => 'RC-2026-888888',
        'passport_number' => 'UNRELATED01',
        'status' => ApplicationStatus::PendingApproval,
    ]);

    // 3. Create drafts
    ApplicationDraft::create([
        'applicant_id' => $applicant1->id,
        'type' => 'NEW',
        'current_step' => 3,
        'data' => ['email' => 'ezeab.nis@gmail.com', 'passport_number' => 'TESTPASS001'],
    ]);

    ApplicationDraft::create([
        'applicant_id' => $applicant2->id,
        'type' => 'NEW',
        'current_step' => 1,
        'data' => ['email' => 'blessingeze00034@gmail.com'],
    ]);

    $unrelatedDraft = ApplicationDraft::create([
        'applicant_id' => $unrelatedApplicant->id,
        'type' => 'NEW',
        'current_step' => 2,
        'data' => ['email' => 'unrelated.applicant@example.com'],
    ]);

    // 4. Create cards, payments, notes, status histories
    $card1 = ResidenceCard::factory()->create([
        'applicant_id' => $applicant1->id,
        'passport_number' => 'TESTPASS001',
        'decision_reference' => 'RC-2026-999001',
    ]);
    $principalApp->update(['card_id' => $card1->id]);

    $unrelatedCard = ResidenceCard::factory()->create([
        'applicant_id' => $unrelatedApplicant->id,
        'passport_number' => 'UNRELATED01',
        'decision_reference' => 'RC-2026-888888',
    ]);
    $unrelatedApp->update(['card_id' => $unrelatedCard->id]);

    Payment::create([
        'applicant_id' => $applicant1->id,
        'application_id' => $principalApp->id,
        'reference' => 'PAY-TEST-999001',
        'amount_kobo' => 3500000,
        'status' => 'SUCCESS',
    ]);

    $unrelatedPayment = Payment::create([
        'applicant_id' => $unrelatedApplicant->id,
        'application_id' => $unrelatedApp->id,
        'reference' => 'PAY-UNRELATED-888888',
        'amount_kobo' => 3500000,
        'status' => 'SUCCESS',
    ]);

    // 5. Execute reset
    $summary = TestAccountResetter::reset();

    // Verify deletion summary
    expect($summary['applications_deleted'])->toBeGreaterThanOrEqual(3);
    expect($summary['drafts_deleted'])->toBeGreaterThanOrEqual(2);
    expect($summary['cards_deleted'])->toBeGreaterThanOrEqual(1);

    // Verify test applications are gone
    expect(Application::where('email', 'ezeab.nis@gmail.com')->count())->toBe(0);
    expect(Application::where('email', 'blessingeze00034@gmail.com')->count())->toBe(0);
    expect(Application::where('passport_number', 'TESTPASS001')->count())->toBe(0);
    expect(Application::where('passport_number', 'DEP-PASS-001')->count())->toBe(0);
    expect(ApplicationDraft::where('applicant_id', $applicant1->id)->count())->toBe(0);
    expect(ApplicationDraft::where('applicant_id', $applicant2->id)->count())->toBe(0);
    expect(ResidenceCard::where('passport_number', 'TESTPASS001')->count())->toBe(0);
    expect(Payment::where('reference', 'PAY-TEST-999001')->count())->toBe(0);

    // Verify accounts are preserved and available for login
    expect(Applicant::where('email', 'ezeab.nis@gmail.com')->exists())->toBeTrue();
    expect(Applicant::where('email', 'blessingeze00034@gmail.com')->exists())->toBeTrue();
    $applicant1->refresh();
    expect($applicant1->passport_number)->toBeNull();

    // Verify unrelated data is completely intact
    expect(Application::where('id', $unrelatedApp->id)->count())->toBe(1);
    expect(ApplicationDraft::where('id', $unrelatedDraft->id)->count())->toBe(1);
    expect(ResidenceCard::where('id', $unrelatedCard->id)->count())->toBe(1);
    expect(Payment::where('id', $unrelatedPayment->id)->count())->toBe(1);
    expect(Applicant::where('id', $unrelatedApplicant->id)->count())->toBe(1);
});

it('can run test:reset-accounts artisan command', function () {
    $this->artisan('test:reset-accounts --force')
        ->assertSuccessful();
});
