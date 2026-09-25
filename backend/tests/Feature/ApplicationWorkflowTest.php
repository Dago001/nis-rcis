<?php

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\EnrollmentCenter;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\VerifyApplicantEmail;
use App\Support\OAuthClients;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

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

it('registers an applicant and only verifies through the e-mailed signed link', function () {
    Notification::fake();

    $this->postJson('/api/v1/applicant/register', [
        'surname' => 'Okafor', 'forenames' => 'Jean', 'email' => 'JP@example.com', 'phone' => '+2348022222222',
        'password' => 'Sufficiently-long-9', 'password_confirmation' => 'Sufficiently-long-9',
    ])->assertAccepted()->assertJsonMissingPath('token');

    $applicant = Applicant::where('email', 'jp@example.com')->firstOrFail();
    expect($applicant->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($applicant, VerifyApplicantEmail::class);

    // Registering the same address again reveals nothing.
    $this->postJson('/api/v1/applicant/register', [
        'surname' => 'X', 'forenames' => 'Y', 'email' => 'jp@example.com', 'phone' => '+2348022222222',
        'password' => 'Sufficiently-long-9', 'password_confirmation' => 'Sufficiently-long-9',
    ])->assertAccepted();

    $url = URL::temporarySignedRoute('applicant.verify', now()->addHour(), ['id' => $applicant->id, 'hash' => sha1('jp@example.com')]);
    $this->get($url)->assertRedirect('http://portal.test/login?verified=1');
    expect($applicant->fresh()->hasVerifiedEmail())->toBeTrue();

    // Tampered link is rejected.
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
});

it('runs the complete legacy workflow from online application to card collection', function () {
    Notification::fake();
    $applicant = Applicant::factory()->create(['email' => 'jp@example.com']);
    $approver = User::factory()->role(StaffRole::ApprovingOfficer)->create();
    $issuer = User::factory()->role(StaffRole::IssuingOfficer)->create();

    // --- Applicant: draft, documents, payment, submit
    asApplicant($applicant);
    $this->putJson('/api/v1/applicant/draft', ['type' => 'NEW', 'current_step' => 3, 'data' => ['surname' => 'OKAFOR']])->assertOk();
    uploadDraftDocs();
    $reference = paidReference();

    $app = $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => $reference]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'PENDING_APPROVAL')
        ->json('data');

    expect($app['application_number'])->toMatch('/^RC-\d{4}-\d{6}$/')
        ->and($app['documents'])->toHaveCount(3);
    $this->getJson('/api/v1/applicant/draft')->assertJsonPath('draft', null);

    // Payment cannot be reused.
    $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => $reference, 'passport_number' => 'CM7654321']))
        ->assertUnprocessable();

    // --- Issuing officer cannot decide; approving officer queries
    asStaff($issuer);
    $this->postJson("/api/v1/staff/applications/{$app['id']}/decision", ['decision' => 'APPROVE'])->assertForbidden();

    asStaff($approver);
    $this->postJson("/api/v1/staff/applications/{$app['id']}/decision", ['decision' => 'QUERY'])->assertUnprocessable(); // notes required
    $this->postJson("/api/v1/staff/applications/{$app['id']}/decision", ['decision' => 'QUERY', 'notes' => 'Visa copy is illegible'])
        ->assertOk()->assertJsonPath('data.status', 'QUERIED');

    // --- Applicant re-uploads and responds
    asApplicant($applicant);
    $this->post("/api/v1/applicant/applications/{$app['id']}/documents", [
        'type' => 'residence_visa', 'file' => UploadedFile::fake()->createWithContent('visa2.png', $this->pngBytes()),
    ], ['Accept' => 'application/json'])->assertOk();
    $this->postJson("/api/v1/applicant/applications/{$app['id']}/respond", ['response' => 'Clearer copy uploaded'])
        ->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL');

    // --- Approve for biometrics; cannot approve twice
    asStaff($approver);
    $this->postJson("/api/v1/staff/applications/{$app['id']}/decision", ['decision' => 'APPROVE'])
        ->assertOk()->assertJsonPath('data.status', 'APPROVED_FOR_BIOMETRICS');
    $this->postJson("/api/v1/staff/applications/{$app['id']}/decision", ['decision' => 'REJECT', 'notes' => 'x'])
        ->assertUnprocessable();

    // --- Biometrics desk creates the card (awaiting final approval)
    asStaff($issuer);
    $captured = $this->postJson("/api/v1/staff/applications/{$app['id']}/biometrics", [
        'photo' => $this->pngDataUrl(), 'signature' => $this->pngDataUrl(),
    ])->assertOk()->assertJsonPath('data.status', 'BIOMETRICS_CAPTURED')->json('data');

    $card = ResidenceCard::findOrFail($captured['card']['id']);
    expect($card->status->value)->toBe('APPROVED')
        // Numbers come from a PostgreSQL sequence (legacy series starting at 389108),
        // which other tests may already have advanced.
        ->and((int) $card->card_number)->toBeGreaterThanOrEqual(389108)
        ->and($card->booklet_number)->toBe("RC-{$card->card_number}/".now()->format('y'))
        ->and($card->applicant_id)->toBe($applicant->id);

    // Cannot mark ready before final approval; issuer cannot approve the card.
    $this->postJson("/api/v1/staff/cards/{$card->id}/ready-for-collection")->assertUnprocessable();
    $this->postJson("/api/v1/staff/cards/{$card->id}/decision", ['decision' => 'APPROVE'])->assertForbidden();

    asStaff($approver);
    $this->postJson("/api/v1/staff/cards/{$card->id}/decision", ['decision' => 'APPROVE'])->assertOk()->assertJsonPath('data.status', 'ISSUED');

    asStaff($issuer);
    $this->postJson("/api/v1/staff/cards/{$card->id}/ready-for-collection")->assertOk()->assertJsonPath('data.status', 'READY_FOR_COLLECTION');
    $this->postJson("/api/v1/staff/applications/{$app['id']}/collect")->assertOk()->assertJsonPath('data.status', 'ISSUED');

    // --- Full history recorded and the applicant was told at every step
    $history = Application::find($app['id'])->statusHistory->pluck('to_status')->map->value->all();
    expect($history)->toBe([
        'PENDING_APPROVAL', 'QUERIED', 'PENDING_APPROVAL', 'APPROVED_FOR_BIOMETRICS',
        'BIOMETRICS_CAPTURED', 'READY_FOR_COLLECTION', 'ISSUED',
    ]);
    Notification::assertSentToTimes($applicant, ApplicationStatusChanged::class, 7);

    // --- Public verification by QR token
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/public/verify-card?token='.$card->verification_token)
        ->assertOk()->assertJsonPath('status', 'VALID')->assertJsonPath('card_number', $card->card_number);
});

it('keeps applicants inside their own records', function () {
    $owner = Applicant::factory()->create();
    $other = Applicant::factory()->create();
    asApplicant($owner);
    uploadDraftDocs();
    $id = $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => paidReference()]))->json('data.id');

    asApplicant($other);
    $this->getJson("/api/v1/applicant/applications/{$id}")->assertNotFound();
    $this->getJson("/api/v1/applicant/applications/{$id}/slip")->assertNotFound();
});

it('refuses submission without verified payment or required documents', function () {
    $applicant = Applicant::factory()->create();
    asApplicant($applicant);

    $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => 'NOPE']))
        ->assertUnprocessable()->assertJsonValidationErrors('documents');

    uploadDraftDocs();
    $unpaid = $this->postJson('/api/v1/applicant/payments')->json('reference');
    $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => $unpaid]))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_reference');
});

it('rejects forged file types', function () {
    asApplicant(Applicant::factory()->create());

    $this->post('/api/v1/applicant/draft/documents', [
        'type' => 'passport_copy',
        'file' => UploadedFile::fake()->createWithContent('passport.pdf', '<?php echo "pwned";'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

it('only lets applicants renew their own card', function () {
    $applicant = Applicant::factory()->create();
    $card = ResidenceCard::factory()->create(['passport_number' => 'CM1234567', 'applicant_id' => null]);
    asApplicant($applicant);
    uploadDraftDocs();

    $this->postJson('/api/v1/applicant/applications', particulars([
        'type' => 'RENEWAL', 'renewal_card_number' => $card->card_number, 'passport_number' => 'ZZ9999999',
        'payment_reference' => paidReference(),
    ]))->assertUnprocessable()->assertJsonValidationErrors('renewal_card_number');

    $this->postJson('/api/v1/applicant/applications', particulars([
        'type' => 'RENEWAL', 'renewal_card_number' => $card->card_number, 'payment_reference' => paidReference(),
    ]))->assertCreated()->assertJsonPath('data.type', 'RENEWAL');

    expect($card->fresh()->applicant_id)->toBe($applicant->id);
});

it('requires both application and passport number for public tracking', function () {
    asApplicant(Applicant::factory()->create());
    uploadDraftDocs();
    $number = $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => paidReference()]))
        ->json('data.application_number');
    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/public/track?application_number={$number}")->assertUnprocessable();
    $this->getJson("/api/v1/public/track?application_number={$number}&passport_number=XX0000000")->assertNotFound();
    $this->getJson("/api/v1/public/track?application_number={$number}&passport_number=CM1234567")
        ->assertOk()->assertJsonPath('holder', 'O***** J*** P*****')->assertJsonMissingPath('date_of_birth');
});

it('enforces card security roles and keeps watchlist reasons from the public', function () {
    $card = ResidenceCard::factory()->issued()->create();
    $inspector = User::factory()->role(StaffRole::Inspector)->create();
    $approver = User::factory()->role(StaffRole::ApprovingOfficer)->create();
    $admin = User::factory()->role(StaffRole::SuperAdmin)->create();

    asStaff($inspector);
    $this->postJson("/api/v1/staff/cards/{$card->id}/revoke", ['reason' => 'x'])->assertForbidden();

    asStaff($approver);
    $this->postJson("/api/v1/staff/cards/{$card->id}/watchlist", ['watchlisted' => true, 'reason' => 'Interpol notice'])->assertOk();
    $this->postJson("/api/v1/staff/cards/{$card->id}/revoke", ['reason' => 'Fraudulent documents'])->assertOk()->assertJsonPath('data.status', 'REVOKED');
    $this->postJson("/api/v1/staff/cards/{$card->id}/reinstate")->assertForbidden();

    asStaff($admin);
    $this->postJson("/api/v1/staff/cards/{$card->id}/reinstate")->assertOk()->assertJsonPath('data.status', 'ISSUED');

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/public/verify-card?card_number={$card->card_number}&passport_number={$card->passport_number}")
        ->assertOk()->assertJsonPath('status', 'REFER_TO_NIS')->assertJsonMissing(['Interpol notice']);
});

it('renews a card with an endorsement record', function () {
    $card = ResidenceCard::factory()->issued()->create(['expires_on' => now()->addMonth()]);
    asStaff(User::factory()->role(StaffRole::IssuingOfficer)->create());

    $this->postJson("/api/v1/staff/cards/{$card->id}/renew", [
        'from_date' => now()->addMonth()->addDay()->toDateString(),
        'to_date' => now()->addYears(2)->toDateString(),
        'fee_paid_naira' => 35000, 'receipt_number' => 'RCPT-1',
    ])->assertOk()->assertJsonPath('data.status', 'RENEWED')->assertJsonPath('data.renewals.0.renewal_number', 1);
});

it('keeps the audit log append-only', function () {
    asStaff(User::factory()->role(StaffRole::SuperAdmin)->create());
    $this->postJson('/api/v1/staff/users', [
        'username' => 'officer1', 'fullname' => 'Ngozi Adeleke', 'service_number' => '19102',
        'email' => 'ngozi@example.com', 'role' => 'IssuingOfficer', 'command' => 'Lagos',
    ])->assertCreated()->assertJsonStructure(['temporary_password']);

    expect(AuditLog::where('action', 'STAFF_CREATED')->exists())->toBeTrue();
    expect(fn () => DB::table('audit_logs')->update(['action' => 'TAMPERED']))->toThrow(Exception::class);
});

it('blocks deactivated staff tokens and restricts auditors', function () {
    $auditor = User::factory()->role(StaffRole::Auditor)->create();
    asStaff($auditor);
    $this->getJson('/api/v1/staff/audit-logs')->assertOk();
    $this->getJson('/api/v1/staff/users')->assertForbidden();

    $auditor->update(['is_active' => false]);
    asStaff($auditor->fresh());
    $this->getJson('/api/v1/staff/me')->assertForbidden();
});

it('refuses every out-of-order workflow step', function () {
    asApplicant(Applicant::factory()->create());
    uploadDraftDocs();
    $id = $this->postJson('/api/v1/applicant/applications', particulars(['payment_reference' => paidReference()]))->json('data.id');

    // Applicant cannot "respond" unless queried.
    $this->postJson("/api/v1/applicant/applications/{$id}/respond", ['response' => 'hi'])->assertUnprocessable();

    asStaff(User::factory()->role(StaffRole::SuperAdmin)->create());
    // Pending application: no collection, no biometrics.
    $this->postJson("/api/v1/staff/applications/{$id}/collect")->assertUnprocessable();
    $this->postJson("/api/v1/staff/applications/{$id}/biometrics", ['photo' => $this->pngDataUrl(), 'signature' => $this->pngDataUrl()])
        ->assertUnprocessable();

    // Rejected is final.
    $this->postJson("/api/v1/staff/applications/{$id}/decision", ['decision' => 'REJECT', 'notes' => 'Forged visa'])->assertOk();
    $this->postJson("/api/v1/staff/applications/{$id}/decision", ['decision' => 'APPROVE'])->assertUnprocessable();

    expect(Application::find($id)->status->value)->toBe('REJECTED');
});

it('rejects mixed scope requests at the token endpoint', function () {
    [$client, $secret] = OAuthClients::partner('Customs');

    $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->id, 'client_secret' => $secret, 'scope' => 'cards:verify staff',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
});

it('builds e-mail links from APP_URL even when called on an internal host', function () {
    Notification::fake();
    config(['app.url' => 'https://api.rcis.example']);
    URL::forceRootUrl('https://api.rcis.example');

    $this->withServerVariables(['HTTP_HOST' => '127.0.0.1:8000'])->postJson('/api/v1/applicant/register', [
        'surname' => 'A', 'forenames' => 'B', 'email' => 'ab@example.com', 'phone' => '+2348022222222',
        'password' => 'Sufficiently-long-9', 'password_confirmation' => 'Sufficiently-long-9',
    ])->assertAccepted();

    Notification::assertSentTo(Applicant::first(), VerifyApplicantEmail::class, function ($n, $channels, $notifiable) {
        return parse_url($n->toMail($notifiable)->actionUrl, PHP_URL_HOST) === 'api.rcis.example';
    });
});

it('loads demo data once and never in production', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $this->artisan('nis:demo')->expectsOutputToContain('already loaded')->assertSuccessful();

    expect(Application::pluck('status')->map->value->sort()->values()->all())->toBe([
        'APPROVED_FOR_BIOMETRICS', 'BIOMETRICS_CAPTURED', 'ISSUED', 'PENDING_APPROVAL', 'QUERIED',
    ]);
    expect(ResidenceCard::count())->toBe(2);

    app()->detectEnvironment(fn () => 'production');
    $this->artisan('nis:demo')->assertFailed();
});
