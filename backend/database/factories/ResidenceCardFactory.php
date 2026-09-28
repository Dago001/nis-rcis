<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\ResidenceCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidenceCard>
 */
class ResidenceCardFactory extends Factory
{
    protected $model = ResidenceCard::class;

    public function definition(): array
    {
        return [
            'booklet_number' => 'RC-'.fake()->unique()->numerify('######').'/26',
            'decision_date' => now()->subMonth(),
            'surname' => strtoupper(fake()->lastName()),
            'forenames' => strtoupper(fake()->firstName()),
            'nationality' => 'GHANA',
            'date_of_birth' => '1980-01-01',
            'place_of_birth' => 'ACCRA',
            'sex' => 'MALE',
            'profession' => 'ENGINEER',
            'domicile' => 'Lagos',
            'passport_number' => strtoupper(fake()->unique()->bothify('G#######')),
            'emergency_contact_name' => 'KOFI MENSAH',
            'emergency_contact_relation' => 'BROTHER',
            'emergency_contact_phone' => '+2348000000000',
            'emergency_contact_address' => 'Accra',
            'issuing_officer_name' => 'IBRAHIM MUSA',
            'issuing_officer_service_no' => '24820',
            'issued_on' => now()->subMonth(),
            'issued_at' => 'ABUJA',
            'expires_on' => now()->addYears(2),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ResidenceCard $card) {
            $card->card_number ??= (string) fake()->unique()->numberBetween(100000, 199999);
            $card->verification_token ??= bin2hex(random_bytes(32));
            $card->status ??= CardStatus::Approved;
        });
    }

    public function issued(): static
    {
        return $this->afterMaking(fn (ResidenceCard $card) => $card->status = CardStatus::Issued);
    }
}
