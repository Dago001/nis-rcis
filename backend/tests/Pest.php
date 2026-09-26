<?php

use App\Models\Applicant;
use App\Models\EnrollmentCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function asApplicant(Applicant $applicant): void
{
    app('auth')->forgetGuards();
    Passport::actingAs($applicant, ['applicant'], 'applicant-api');
}

function asStaff(User $user): void
{
    app('auth')->forgetGuards();
    Passport::actingAs($user, ['staff'], 'api');
}

// Shared helpers for the application workflow tests.

function particulars(array $overrides = []): array
{
    $center = EnrollmentCenter::first();
    $date = now()->addDays(3);
    while ($date->isWeekend()) {
        $date = $date->addDay();
    }

    return array_merge([
        'type' => 'NEW',
        'surname' => 'Okafor', 'forenames' => 'Jean Pierre', 'nationality' => 'CAMEROON',
        'date_of_birth' => '1985-04-12', 'place_of_birth' => 'Douala', 'sex' => 'MALE',
        'profession' => 'Civil Engineer', 'domicile' => '12 Adeola Odeku Street, Victoria Island',
        'domicile_state' => 'Lagos', 'domicile_lga' => 'Eti Osa',
        'emergency_contact_state' => 'Federal Capital Territory', 'emergency_contact_lga' => 'Bwari',
        'passport_number' => 'CM1234567', 'passport_expiry' => now()->addYears(3)->toDateString(),
        'emergency_contact_name' => 'Marie Okafor', 'emergency_contact_relation' => 'Spouse',
        'emergency_contact_phone' => '+2348011111111', 'emergency_contact_address' => '12 Adeola Odeku Street, Lagos',
        'phone' => '+2348022222222', 'email' => 'jp@example.com',
        'enrollment_center_id' => $center->id, 'appointment_date' => $date->toDateString(), 'appointment_time' => '10:00',
        'declaration' => true,
    ], $overrides);
}

function uploadDraftDocs(): void
{
    foreach (['photo' => 'photo.png', 'passport_copy' => 'passport.png', 'residence_visa' => 'visa.png'] as $type => $name) {
        test()->post('/api/v1/applicant/draft/documents', [
            'type' => $type, 'file' => UploadedFile::fake()->createWithContent($name, test()->pngBytes()),
        ], ['Accept' => 'application/json'])->assertCreated();
    }
}

function paidReference(): string
{
    $reference = test()->postJson('/api/v1/applicant/payments')->assertCreated()->json('reference');
    test()->postJson("/api/v1/applicant/payments/{$reference}/verify")->assertOk()->assertJsonPath('paid', true);

    return $reference;
}
