<?php

use App\Models\Applicant;
use App\Models\EnrollmentCenter;
use App\Support\NigeriaLgas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    RateLimiter::clear('registration');
});

it('has all 36 states plus the FCT and 774 local government areas', function () {
    expect(NigeriaLgas::states())->toHaveCount(37)->toContain('Katsina', 'Federal Capital Territory');
    expect(array_sum(array_map('count', NigeriaLgas::all())))->toBe(774);
});

it('validates names as letters and phones as digits at registration, with 6-character passwords', function () {
    $this->postJson('/api/v1/applicant/register', [
        'surname' => 'Doe1', 'forenames' => 'Jane<script>', 'email' => 'jane@example.com', 'phone' => '0803-abc',
        'password' => 'Nis2026x', 'password_confirmation' => 'Nis2026x',
    ])->assertUnprocessable()->assertJsonValidationErrors(['surname', 'forenames', 'phone']);

    $this->postJson('/api/v1/applicant/register', [
        'surname' => "O'Neil-Smith", 'forenames' => 'Zoë Anne', 'email' => 'jane@example.com', 'phone' => '+2348031234567',
        'password' => 'Nis26x', 'password_confirmation' => 'Nis26x',
    ])->assertAccepted();
});

it('rejects a local government area that is not in the chosen state', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $applicant = Applicant::where('email', 'kwame.mensah@example.com')->firstOrFail();
    asApplicant($applicant);

    $this->postJson('/api/v1/applicant/applications', [
        'domicile_state' => 'Lagos', 'domicile_lga' => 'Bwari',
        'emergency_contact_state' => 'Atlantis', 'emergency_contact_lga' => 'Ikeja',
        'emergency_contact_phone' => '0803 123', 'emergency_contact_relation' => 'Friend 2',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'domicile_lga', 'emergency_contact_state', 'emergency_contact_lga', 'emergency_contact_phone', 'emergency_contact_relation',
    ]);
});

it('only offers NIS Headquarters, Abuja for biometrics', function () {
    $this->getJson('/api/v1/public/enrollment-centers')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'NIS Headquarters, Abuja');
    expect(EnrollmentCenter::where('is_active', true)->count())->toBe(1);
});

it('refuses documents of 2 MB or more', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    asApplicant(Applicant::where('email', 'kwame.mensah@example.com')->firstOrFail());

    $this->post('/api/v1/applicant/draft/documents', [
        'type' => 'passport_copy', 'file' => UploadedFile::fake()->create('big.pdf', 2100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('lets applicants view only their own draft documents', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $owner = Applicant::where('email', 'kwame.mensah@example.com')->firstOrFail();
    asApplicant($owner);
    $id = $this->post('/api/v1/applicant/draft/documents', [
        'type' => 'photo', 'file' => UploadedFile::fake()->createWithContent('me.png', $this->pngBytes()),
    ], ['Accept' => 'application/json'])->assertCreated()->json('draft.documents.0.id');

    $this->getJson("/api/v1/applicant/draft/documents/{$id}")->assertOk()->assertJsonStructure(['url', 'mime_type']);

    asApplicant(Applicant::where('email', 'amara.diallo@example.com')->firstOrFail());
    $this->getJson("/api/v1/applicant/draft/documents/{$id}")->assertNotFound();
});

it('lets the website embed signed file links from another origin, but nothing else', function () {
    Route::get('/api/v1/public/_signed-file', fn () => 'file')->name('test.signed-file');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get(URL::temporarySignedRoute('test.signed-file', now()->addMinute()))
        ->assertOk()->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    $this->get('/api/v1/public/_signed-file?signature=forged')->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
    $this->getJson('/api/v1/public/enrollment-centers')->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
});
