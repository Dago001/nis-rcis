<?php

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\ErrorEvent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\SystemAlert;
use App\Services\ErrorTracker;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => config(['nis.alerts.disk_min_free_percent' => 0]));

it('reports overall health to uptime monitors without details', function () {
    $response = $this->getJson('/api/v1/health')->assertOk();
    expect($response->json('status'))->toBeIn(['ok', 'warn'])
        ->and($response->json())->not->toHaveKey('checks');
});

it('shows detailed health and tracked errors to Super Administrators only', function () {
    asStaff(User::factory()->role(StaffRole::ApprovingOfficer)->create());
    $this->getJson('/api/v1/staff/system/health')->assertForbidden();

    asStaff(User::factory()->role(StaffRole::SuperAdmin)->create());
    $this->getJson('/api/v1/staff/system/health')->assertOk()
        ->assertJsonPath('checks.0.name', 'Database')->assertJsonPath('checks.0.status', 'ok');
});

it('groups server errors, e-mails new ones once, and alerts again when a resolved error returns', function () {
    Notification::fake();
    config(['nis.alerts.emails' => ['ops@example.gov.ng']]);
    $tracker = app(ErrorTracker::class);
    $boom = fn () => new RuntimeException('Card printer offline for passport 123456789 (officer jane@example.com)');

    $tracker->capture($e = $boom());
    $tracker->capture($e);
    $event = ErrorEvent::sole();
    expect($event->occurrences)->toBe(2)
        ->and($event->message)->not->toContain('123456789')->not->toContain('jane@example.com');
    Notification::assertSentOnDemandTimes(SystemAlert::class, 1);

    asStaff(User::factory()->role(StaffRole::SuperAdmin)->create());
    $this->postJson("/api/v1/staff/system/errors/{$event->id}/resolve")->assertOk();
    $event->forceFill(['alerted_at' => now()->subHours(2)])->save();
    $tracker->capture($e);
    Notification::assertSentOnDemandTimes(SystemAlert::class, 2);
    expect($event->fresh()->resolved_at)->toBeNull();
});

it('e-mails an alert when a health check starts failing and when it recovers', function () {
    Notification::fake();
    // Pretend the disk is nearly full so the rule fails, then recovers.
    config(['nis.alerts.emails' => ['ops@example.gov.ng'], 'nis.alerts.disk_min_free_percent' => 101, 'nis.alerts.disk_min_free_gb' => 1_000_000]);
    $this->artisan('nis:health-check');
    config(['nis.alerts.disk_min_free_percent' => 0]);
    $this->artisan('nis:health-check');

    Notification::assertSentOnDemand(SystemAlert::class, fn (SystemAlert $n) => str_contains($n->subject, 'Health check failing:') && str_contains($n->subject, 'Disk space'));
    Notification::assertSentOnDemand(SystemAlert::class, fn (SystemAlert $n) => str_starts_with($n->subject, 'Recovered:') && str_contains($n->subject, 'Disk space'));
});

it('lets applicants turn push notifications on for a device, sent alongside the e-mail', function () {
    config(['nis.push.public_key' => 'BJv-test', 'nis.push.private_key' => 'test']);
    $applicant = Applicant::factory()->create();
    asApplicant($applicant);

    $this->getJson('/api/v1/public/push-key')->assertOk()->assertJsonPath('public_key', 'BJv-test');
    $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BPkey', 'auth' => 'authkey']];
    $this->postJson('/api/v1/applicant/push-subscriptions', [...$sub, 'endpoint' => 'http://insecure.test/x'])->assertUnprocessable();
    $this->postJson('/api/v1/applicant/push-subscriptions', $sub)->assertCreated();
    $this->postJson('/api/v1/applicant/push-subscriptions', $sub)->assertCreated();
    expect(PushSubscription::count())->toBe(1)->and(WebPushChannel::enabled($applicant))->toBeTrue();

    $this->deleteJson('/api/v1/applicant/push-subscriptions', ['endpoint' => $sub['endpoint']])->assertNoContent();
    expect(WebPushChannel::enabled($applicant))->toBeFalse();
});
