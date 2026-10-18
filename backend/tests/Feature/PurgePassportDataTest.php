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
use App\Services\PassportDataPurger;
use Illuminate\Support\Facades\DB;

it('purges an application, card, documents, notes and draft associated with a passport completely', function () {
    $applicant = Applicant::factory()->create([
        'passport_number' => 'B6556610',
    ]);

    $app = Application::factory()->create([
        'applicant_id' => $applicant->id,
        'application_number' => 'RC-2026-100016',
        'passport_number' => 'B6556610',
        'status' => ApplicationStatus::PendingApproval,
    ]);

    $officer = User::factory()->create();

    ApplicationStatusHistory::create([
        'application_id' => $app->id,
        'to_status' => ApplicationStatus::PendingApproval->value,
    ]);

    ApplicationNote::create([
        'application_id' => $app->id,
        'user_id' => $officer->id,
        'body' => 'Test query notes for B6556610',
    ]);

    Payment::create([
        'applicant_id' => $applicant->id,
        'application_id' => $app->id,
        'reference' => 'PAY-B6556610-TEST',
        'amount_kobo' => 3500000,
        'status' => 'SUCCESS',
    ]);

    $card = ResidenceCard::factory()->create([
        'applicant_id' => $applicant->id,
        'passport_number' => 'B6556610',
        'decision_reference' => 'RC-2026-100016',
    ]);

    $app->update(['card_id' => $card->id]);

    ApplicationDraft::create([
        'applicant_id' => $applicant->id,
        'type' => 'RENEWAL',
        'current_step' => 2,
        'data' => [
            'passport_number' => 'B6556610',
            'application_number' => 'RC-2026-100016',
        ],
    ]);

    // Verify records exist before purge
    expect(Application::where('passport_number', 'B6556610')->count())->toBe(1);
    expect(ResidenceCard::where('passport_number', 'B6556610')->count())->toBe(1);
    expect(ApplicationDraft::count())->toBe(1);

    // Run purge
    $summary = PassportDataPurger::purge('B6556610', 'RC-2026-100016');

    expect($summary['applications_deleted'])->toBe(1);
    expect($summary['cards_deleted'])->toBe(1);
    expect(Application::where('passport_number', 'B6556610')->count())->toBe(0);
    expect(Application::where('application_number', 'RC-2026-100016')->count())->toBe(0);
    expect(ResidenceCard::where('passport_number', 'B6556610')->count())->toBe(0);
    expect(Payment::where('application_id', $app->id)->count())->toBe(0);
    expect(ApplicationNote::where('application_id', $app->id)->count())->toBe(0);
    expect(ApplicationStatusHistory::where('application_id', $app->id)->count())->toBe(0);
    expect(ApplicationDraft::count())->toBe(0);

    // Applicant's profile passport_number should be cleared
    $applicant->refresh();
    expect($applicant->passport_number)->toBeNull();
});

it('can run nis:purge-passport command via artisan', function () {
    $app = Application::factory()->create([
        'application_number' => 'RC-2026-100016',
        'passport_number' => 'B6556610',
    ]);

    $this->artisan('nis:purge-passport B6556610 --application=RC-2026-100016 --force')
        ->assertSuccessful();

    expect(Application::where('passport_number', 'B6556610')->count())->toBe(0);
});
