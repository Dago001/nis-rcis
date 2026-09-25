<?php

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
