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

    $response = $this->actingAs($user, 'staff')->get('/staff/applications');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Staff/Applications'));
});
