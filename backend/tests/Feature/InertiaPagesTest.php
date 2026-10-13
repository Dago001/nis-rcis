<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders public pages via Inertia successfully', function (string $url, string $component) {
    $response = $this->get($url);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/', 'Public/Home'],
    ['/about', 'Public/About'],
    ['/contact', 'Public/Contact'],
    ['/faq', 'Public/Faq'],
    ['/track', 'Public/Track'],
    ['/verify', 'Public/Verify'],
    ['/login', 'Auth/Login'],
]);

it('serves favicon.ico successfully', function () {
    $response = $this->get('/favicon.ico');
    $response->assertOk();
});

it('responds to public chatbot assistant queries', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

    $response = $this->postJson('/api/v1/public/assistant', [
        'message' => 'What documents do I need to apply?',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'reply',
        'source',
        'links',
        'conversation_id',
        'suggestions',
    ]);
});

it('renders applicant portal pages when authenticated', function () {
    $applicant = \App\Models\Applicant::factory()->create();

    $response = $this->actingAs($applicant, 'applicant')->get('/portal/apply');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Portal/Apply'));
});

it('renders staff console pages when authenticated', function () {
    $user = \App\Models\User::factory()->create([
        'role' => \App\Enums\StaffRole::ApprovingOfficer,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'staff')->get('/staff');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Staff/Dashboard'));

    $response = $this->actingAs($user, 'staff')->get('/staff/applications');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Staff/Applications'));

    $apiResponse = $this->actingAs($user, 'staff')->getJson('/api/v1/staff/me');
    $apiResponse->assertOk()->assertJsonPath('role', 'ApprovingOfficer');
});

it('redirects staff to staff console even if session had applicant intended url', function () {
    $secret = \App\Support\Totp::generateSecret();
    $user = \App\Models\User::factory()->create([
        'service_number' => '99887',
        'password' => 'StaffPassword123!',
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
        'is_active' => true,
    ]);

    // 1. Session contains applicant url.intended from previous browsing
    $this->withSession(['url.intended' => 'http://localhost/portal/apply']);

    // 2. User navigates to /login/staff and logs in
    $response = $this->post('/login/staff', [
        'identifier' => '99887',
        'password' => 'StaffPassword123!',
        '_token' => csrf_token(),
    ]);
    $response->assertRedirect(route('login.staff.two-factor'));

    // 3. User submits 2FA code
    $code = \App\Support\Totp::code($secret);
    $twoFactorResponse = $this->post('/login/staff/two-factor', [
        'code' => $code,
        '_token' => csrf_token(),
    ]);

    // 4. Must redirect to /staff, NEVER to /portal/apply or /login/applicant
    $twoFactorResponse->assertRedirect('/staff');
    $this->assertAuthenticatedAs($user, 'staff');
});

