<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'fullname' => fake()->name(),
            'service_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Correct-Horse-9-Battery!',
            'role' => StaffRole::IssuingOfficer,
            'command' => 'National Processing Center',
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function role(StaffRole $role): static
    {
        return $this->state(['role' => $role]);
    }
}
