<?php

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\EnrollmentCenter;
use App\Models\User;
use App\Services\TemporaryResidenceCardService;
use Illuminate\Support\Facades\Notification;

it('enforces that Temporary Residence Card is unavailable when biometrics are not completed (Test A, B, C)', function () {
    $service = new TemporaryResidenceCardService();

    // Test A: Draft / Pending Approval / Biometrics not completed
    $appPending = new Application([
        'status' => ApplicationStatus::PendingApproval,
        'biometrics_captured_at' => null,
    ]);
    expect($service->isEligible($appPending))->toBeFalse();

    // Test B: Appointment booked only (ApprovedForBiometrics with appointment date, but no biometrics capture)
    $appBooked = new Application([
        'status' => ApplicationStatus::ApprovedForBiometrics,
        'appointment_date' => '2026-11-01',
        'appointment_time' => '10:00',
        'biometrics_captured_at' => null,
    ]);
    expect($service->isEligible($appBooked))->toBeFalse();

    // Test C: Final approval given (decided_at set), but biometrics not yet captured
    $appApproved = new Application([
        'status' => ApplicationStatus::ApprovedForBiometrics,
        'decided_at' => now(),
        'biometrics_captured_at' => null,
    ]);
    expect($service->isEligible($appApproved))->toBeFalse();
});

it('enforces independent eligibility for principal and dependent applicants (Test D, E)', function () {
    $service = new TemporaryResidenceCardService();

    // Principal applicant with completed biometrics
    $principal = new Application([
        'id' => 101,
        'application_number' => 'RC-2026-100101',
        'reference_number' => 'REF-PRINCIPAL-01',
        'surname' => 'Eze',
        'forenames' => 'Chinedu',
        'nationality' => 'GHANA',
        'passport_number' => 'GHA1234567',
        'status' => ApplicationStatus::BiometricsCaptured,
        'biometrics_captured_at' => now(),
    ]);
    expect($service->isEligible($principal))->toBeTrue();

    // Dependent applicant whose biometrics are NOT yet captured
    $dependentPending = new Application([
        'id' => 102,
        'application_number' => 'RC-2026-100102',
        'reference_number' => 'REF-DEPENDENT-01',
        'principal_application_id' => 101,
        'surname' => 'Eze',
        'forenames' => 'Amaka',
        'nationality' => 'GHANA',
        'passport_number' => 'GHA7654321',
        'status' => ApplicationStatus::ApprovedForBiometrics,
        'biometrics_captured_at' => null,
    ]);
    // Critical Rule: Dependent must NOT receive a card merely because principal completed biometrics
    expect($service->isEligible($dependentPending))->toBeFalse();

    // Dependent applicant who has independently completed biometrics
    $dependentCaptured = new Application([
        'id' => 102,
        'application_number' => 'RC-2026-100102',
        'reference_number' => 'REF-DEPENDENT-01',
        'principal_application_id' => 101,
        'surname' => 'Eze',
        'forenames' => 'Amaka',
        'nationality' => 'GHANA',
        'passport_number' => 'GHA7654321',
        'status' => ApplicationStatus::BiometricsCaptured,
        'biometrics_captured_at' => now(),
    ]);
    expect($service->isEligible($dependentCaptured))->toBeTrue();
});

it('verifies temporary card remains available during card production and collection (Test G)', function () {
    $service = new TemporaryResidenceCardService();

    // Card in production / ready for collection
    $appReady = new Application([
        'status' => ApplicationStatus::ReadyForCollection,
        'biometrics_captured_at' => now()->subDays(5),
    ]);
    expect($service->isEligible($appReady))->toBeTrue();

    // Card issued
    $appIssued = new Application([
        'status' => ApplicationStatus::Issued,
        'biometrics_captured_at' => now()->subDays(10),
    ]);
    expect($service->isEligible($appIssued))->toBeTrue();
});

it('generates accurate QR payload and 90-day validity policy data', function () {
    $service = new TemporaryResidenceCardService();
    $storage = app(\App\Services\DocumentStorage::class);

    $app = new Application([
        'id' => 201,
        'application_number' => 'RC-2026-200201',
        'reference_number' => 'REF-200201',
        'surname' => 'Koffi',
        'forenames' => 'Kwame',
        'nationality' => 'TOGO',
        'passport_number' => 'TG9876543',
        'category' => 'BUSINESS',
        'domicile' => '45 Marina Street, Lagos',
        'status' => ApplicationStatus::BiometricsCaptured,
        'biometrics_captured_at' => now(),
    ]);

    $data = $service->getData($app, $storage);

    expect($data['temporary_card_number'])->toBe('TRC-RC-2026-200201')
        ->and($data['application_number'])->toBe('RC-2026-200201')
        ->and($data['reference_number'])->toBe('REF-200201')
        ->and($data['applicant_name'])->toBe('Koffi, Kwame')
        ->and($data['nationality'])->toBe('TOGO')
        ->and($data['passport_number'])->toBe('TG9876543')
        ->and($data['category'])->toBe('BUSINESS')
        ->and($data['validity_period'])->toBe('90 Days')
        ->and($data['qr_payload'])->toContain('NIS-TRC')
        ->and($data['qr_payload'])->toContain('STATUS:VALID')
        ->and($data['is_development_preview'])->toBeTrue()
        ->and($data['disclaimer'])->toContain('Development Preview');
});
