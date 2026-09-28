<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only. Staff accounts are created with
     * `php artisan nis:create-staff` (no default passwords) and OAuth
     * clients with `php artisan nis:oauth-clients`.
     */
    public function run(): void
    {
        $this->call(EnrollmentCenterSeeder::class);
    }
}
