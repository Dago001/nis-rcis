<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\EnrollmentCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'enrollment_center_id' => fn () => EnrollmentCenter::first()?->id ?? 1,
            'type' => 'NEW',
            'channel' => 'ONLINE',
            'category' => 'WORK',
            'status' => ApplicationStatus::PendingApproval,
            'payment_status' => 'PAID',
            'surname' => strtoupper(fake()->lastName()),
            'forenames' => strtoupper(fake()->firstName()),
            'nationality' => 'GHANA',
            'date_of_birth' => '1985-05-15',
            'place_of_birth' => 'ACCRA',
            'sex' => 'MALE',
            'profession' => 'ENGINEER',
            'home_country_address' => '15 Independence Avenue, Accra',
            'domicile' => '12 Adeola Odeku Street, Victoria Island',
            'domicile_state' => 'Lagos',
            'domicile_lga' => 'Eti Osa',
            'passport_number' => strtoupper(fake()->unique()->bothify('G#######')),
            'passport_issue_date' => now()->subYears(2)->toDateString(),
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'emergency_contact_name' => 'KOFI MENSAH',
            'emergency_contact_relation' => 'BROTHER',
            'emergency_contact_phone' => '+2348000000000',
            'emergency_contact_address' => 'Accra',
            'emergency_contact_state' => 'Federal Capital Territory',
            'emergency_contact_lga' => 'Bwari',
            'phone' => '+2348022222222',
            'email' => fake()->safeEmail(),
            'appointment_date' => now()->addDays(5)->toDateString(),
            'appointment_time' => '10:00',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Application $app) {
            $num = fake()->unique()->numberBetween(100000, 999999);
            $app->application_number ??= 'AR' . $num;
            $app->reference_number ??= 'REF-' . $num;
        });
    }
}
