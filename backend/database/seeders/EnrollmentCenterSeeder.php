<?php

namespace Database\Seeders;

use App\Models\EnrollmentCenter;
use Illuminate\Database\Seeder;

class EnrollmentCenterSeeder extends Seeder
{
    public function run(): void
    {
        $slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00'];

        $centers = [
            ['code' => 'ABJ-HQ', 'name' => 'NIS Headquarters, Abuja', 'state' => 'FCT', 'address' => 'Sauka, Airport Road, Abuja'],
            ['code' => 'LAG-ALA', 'name' => 'Alagbon Passport & Residence Office, Lagos', 'state' => 'Lagos', 'address' => 'Alagbon Close, Ikoyi, Lagos'],
            ['code' => 'PHC', 'name' => 'Port Harcourt Zonal Office', 'state' => 'Rivers', 'address' => 'Port Harcourt, Rivers State'],
            ['code' => 'KAN', 'name' => 'Kano State Command', 'state' => 'Kano', 'address' => 'Kano, Kano State'],
        ];

        foreach ($centers as $center) {
            EnrollmentCenter::updateOrCreate(['code' => $center['code']], $center + ['daily_capacity' => 60, 'time_slots' => $slots]);
        }
    }
}
