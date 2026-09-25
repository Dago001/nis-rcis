<?php

namespace Database\Seeders;

use App\Models\EnrollmentCenter;
use Illuminate\Database\Seeder;

class EnrollmentCenterSeeder extends Seeder
{
    public function run(): void
    {
        $slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00'];

        // Residence card biometrics are captured only at NIS Headquarters.
        EnrollmentCenter::updateOrCreate(['code' => 'ABJ-HQ'], [
            'name' => 'NIS Headquarters, Abuja',
            'state' => 'FCT',
            'address' => 'Nigeria Immigration Service Headquarters, Sauka, Airport Road, Abuja',
            'daily_capacity' => 60,
            'time_slots' => $slots,
            'is_active' => true,
        ]);

        // Any other centre (from earlier versions) stays for old records but cannot be booked.
        EnrollmentCenter::where('code', '!=', 'ABJ-HQ')->update(['is_active' => false]);
    }
}
