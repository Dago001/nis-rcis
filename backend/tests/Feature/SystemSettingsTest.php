<?php

use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\SystemSetting;
use App\Models\User;

it('allows SuperAdmin to view and update system broadcast and countdown settings', function () {
    $admin = User::factory()->create([
        'role' => StaffRole::SuperAdmin,
        'is_active' => true,
    ]);

    // 1. Get initial settings
    $res = $this->actingAs($admin, 'staff')->getJson('/api/v1/staff/settings')->assertOk();
    $res->assertJsonStructure([
        'broadcast',
        'countdown',
        'stats' => ['push_subscribers', 'total_applicants', 'total_staff'],
    ]);

    // 2. Update settings
    $future = now()->addDays(2)->toIso8601String();
    $updateRes = $this->actingAs($admin, 'staff')->postJson('/api/v1/staff/settings', [
        'broadcast' => [
            'is_active' => true,
            'title' => 'Scheduled Maintenance Window',
            'message' => 'System maintenance is scheduled for Saturday night.',
            'tone' => 'warning',
            'target' => 'all',
        ],
        'countdown' => [
            'is_active' => true,
            'title' => 'Portal Maintenance Starts In',
            'target_date' => $future,
            'description' => 'Online submissions will pause briefly.',
            'link_label' => 'FAQ',
            'link_url' => '/faq',
        ],
    ])->assertOk();

    expect($updateRes->json('broadcast.is_active'))->toBeTrue()
        ->and($updateRes->json('broadcast.title'))->toBe('Scheduled Maintenance Window')
        ->and($updateRes->json('countdown.is_active'))->toBeTrue()
        ->and($updateRes->json('countdown.title'))->toBe('Portal Maintenance Starts In');

    // 3. Verify public announcement endpoint
    $pub = $this->getJson('/api/v1/public/announcements')->assertOk();
    expect($pub->json('broadcast.is_active'))->toBeTrue()
        ->and($pub->json('countdown.is_active'))->toBeTrue();
});

it('prevents unauthorized staff roles from updating system settings', function () {
    $officer = User::factory()->create([
        'role' => StaffRole::IssuingOfficer,
        'is_active' => true,
    ]);

    $this->actingAs($officer, 'staff')->getJson('/api/v1/staff/settings')->assertForbidden();
    $this->actingAs($officer, 'staff')->postJson('/api/v1/staff/settings', [
        'broadcast' => ['is_active' => true],
    ])->assertForbidden();
});

it('pushes broadcast notifications to registered applicants and staff', function () {
    $admin = User::factory()->create([
        'role' => StaffRole::SuperAdmin,
        'is_active' => true,
    ]);

    $applicant = Applicant::factory()->create();

    $res = $this->actingAs($admin, 'staff')->postJson('/api/v1/staff/settings/push-broadcast', [
        'title' => 'Urgent Security Update',
        'message' => 'Please review your application credentials.',
        'tone' => 'alert',
        'target' => 'all',
    ])->assertOk();

    expect($res->json('success'))->toBeTrue()
        ->and($res->json('recipients_count'))->toBeGreaterThanOrEqual(2);

    // Verify database notification received
    $applicant->refresh();
    expect($applicant->notifications()->count())->toBe(1);
    expect($applicant->notifications()->first()->data['title'])->toBe('Urgent Security Update');
});

it('renders the staff settings page for SuperAdmin', function () {
    $admin = User::factory()->create([
        'role' => StaffRole::SuperAdmin,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'staff')->get('/staff/settings');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Staff/Settings'));
});
