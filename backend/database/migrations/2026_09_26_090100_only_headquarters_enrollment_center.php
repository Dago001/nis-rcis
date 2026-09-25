<?php

use Database\Seeders\EnrollmentCenterSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Biometrics appointments can only be booked at NIS Headquarters, Abuja.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EnrollmentCenterSeeder)->run();
    }

    public function down(): void
    {
        // Nothing to undo: other centres were only deactivated.
    }
};
