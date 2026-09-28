<?php

use App\Enums\ApplicationStatus;
use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Payment;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use App\Notifications\CardExpiryReminder;
use App\Notifications\CardReportedLost;
use App\Notifications\RefundDecided;
use App\Services\PhotoQuality;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

function successfulPayment(Applicant $applicant, array $overrides = []): Payment
{
    return Payment::create([
        'applicant_id' => $applicant->id, 'provider' => 'paystack', 'reference' => 'NIS-'.strtoupper(bin2hex(random_bytes(6))),
        'amount_kobo' => config('nis.fee_naira') * 100, 'currency' => 'NGN', 'status' => 'SUCCESS', 'channel' => 'fake',
        'paid_at' => now(), 'verified_at' => now(), ...$overrides,
    ]);
}

it('refuses blurred, small or dark passport photographs at upload', function () {
    config(['nis.photo_quality_check' => true]);
    asApplicant(Applicant::factory()->create());

    $this->post('/api/v1/applicant/draft/documents', [
        'type' => 'photo', 'file' => UploadedFile::fake()->createWithContent('photo.png', $this->pngBytes()),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

    // A sharp portrait on a light background passes.
    $img = imagecreatetruecolor(400, 500);
    imagefill($img, 0, 0, imagecolorallocate($img, 240, 240, 240));
    imagefilledellipse($img, 200, 230, 200, 260, imagecolorallocate($img, 120, 80, 60));
    for ($y = 300; $y < 500; $y += 6) {
        imageline($img, 60, $y, 340, $y, imagecolorallocate($img, 20, 20, 60));
    }
    ob_start();
    imagejpeg($img, null, 95);
    expect(app(PhotoQuality::class)->problems(ob_get_clean()))->toBe([]);
});

it('lets applicants download PDF receipts for their own payments only', function () {
    $owner = Applicant::factory()->create();
    $payment = successfulPayment($owner);

    asApplicant($owner);
    $this->getJson('/api/v1/applicant/payment-records')->assertOk()
        ->assertJsonPath('data.0.reference', $payment->reference)->assertJsonPath('data.0.refundable', true);
    $response = $this->get("/api/v1/applicant/payment-records/{$payment->reference}/receipt")->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    asApplicant(Applicant::factory()->create());
    $this->get("/api/v1/applicant/payment-records/{$payment->reference}/receipt")->assertNotFound();
});

it('refunds unused payments once, after a Super Administrator approves', function () {
    Notification::fake();
    $applicant = Applicant::factory()->create();
    $payment = successfulPayment($applicant);

    asApplicant($applicant);
    $this->postJson('/api/v1/applicant/refunds', ['payment_reference' => $payment->reference, 'reason' => 'short'])->assertUnprocessable();
    $this->postJson('/api/v1/applicant/refunds', ['payment_reference' => $payment->reference, 'reason' => 'I paid twice by mistake.'])->assertCreated();
    $this->postJson('/api/v1/applicant/refunds', ['payment_reference' => $payment->reference, 'reason' => 'I paid twice by mistake.'])->assertUnprocessable();

    asStaff(User::factory()->role(StaffRole::ApprovingOfficer)->create());
    $this->getJson('/api/v1/staff/refunds')->assertForbidden();

    asStaff(User::factory()->role(StaffRole::SuperAdmin)->create());
    $id = $this->getJson('/api/v1/staff/refunds')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
    $this->postJson("/api/v1/staff/refunds/{$id}/approve")->assertOk()->assertJsonPath('status', 'PROCESSED');
    $this->postJson("/api/v1/staff/refunds/{$id}/approve")->assertStatus(422);

    expect($payment->fresh()->status)->toBe('REFUNDED');
    Notification::assertSentTo($applicant, RefundDecided::class);
});

it('does not refund a payment used for an application that is still being processed', function () {
    $this->artisan('nis:demo')->assertSuccessful();
    $application = Application::where('status', ApplicationStatus::PendingApproval)->whereNotNull('applicant_id')->firstOrFail();
    $payment = successfulPayment($application->applicant, ['application_id' => $application->id]);

    asApplicant($application->applicant);
    $this->postJson('/api/v1/applicant/refunds', ['payment_reference' => $payment->reference, 'reason' => 'Changed my mind about it.'])
        ->assertUnprocessable()->assertJsonValidationErrors('payment_reference');
});

it('lets holders report a card stolen, which invalidates it until an officer clears the report', function () {
    Notification::fake();
    $holder = Applicant::factory()->create();
    $card = ResidenceCard::factory()->issued()->create(['applicant_id' => $holder->id]);
    $other = ResidenceCard::factory()->issued()->create();

    asApplicant($holder);
    $this->getJson('/api/v1/applicant/cards')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_report', true);
    $this->postJson("/api/v1/applicant/cards/{$other->id}/report-lost", ['type' => 'LOST', 'details' => 'Lost at the market.'])->assertNotFound();
    $this->postJson("/api/v1/applicant/cards/{$card->id}/report-lost", ['type' => 'STOLEN', 'details' => 'Bag stolen in Wuse market.'])
        ->assertUnprocessable()->assertJsonValidationErrors('police_report_number');
    $this->postJson("/api/v1/applicant/cards/{$card->id}/report-lost", ['type' => 'STOLEN', 'details' => 'Bag stolen in Wuse market.', 'police_report_number' => 'FCT/WUSE/123'])
        ->assertOk()->assertJsonPath('data.verification_status', 'REPORTED_STOLEN')->assertJsonPath('data.can_replace', true);
    Notification::assertSentTo($holder, CardReportedLost::class);

    $this->getJson("/api/v1/public/verify-card?card_number={$card->card_number}&passport_number={$card->passport_number}")
        ->assertOk()->assertJsonPath('status', 'REPORTED_STOLEN');

    asStaff(User::factory()->role(StaffRole::IssuingOfficer)->create());
    $this->postJson("/api/v1/staff/cards/{$card->id}/clear-lost-report", ['notes' => 'Holder brought the card to HQ'])
        ->assertOk()->assertJsonPath('data.verification_status', 'VALID');
});

it('e-mails appointment reminders the day before and card expiry reminders once per threshold', function () {
    Notification::fake();
    $this->artisan('nis:demo')->assertSuccessful();
    $application = Application::where('status', ApplicationStatus::PendingApproval)->whereNotNull('applicant_id')->firstOrFail();
    $application->forceFill(['appointment_date' => now()->addDay()->toDateString()])->save();
    $holder = Applicant::factory()->create();
    $card = ResidenceCard::factory()->issued()->create(['applicant_id' => $holder->id, 'expires_on' => now()->addDays(25)]);

    $this->artisan('nis:reminders')->assertSuccessful();
    $this->artisan('nis:reminders')->assertSuccessful();

    Notification::assertSentToTimes($application->applicant, AppointmentReminder::class, 1);
    Notification::assertSentToTimes($holder, CardExpiryReminder::class, 1);
    expect($card->fresh()->expiry_reminder_days)->toBe(30);

    $card->forceFill(['expires_on' => now()->addDays(5)])->save();
    $this->artisan('nis:reminders')->assertSuccessful();
    Notification::assertSentToTimes($holder, CardExpiryReminder::class, 2);
});
