<?php

use Database\Seeders\EnrollmentCenterSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensures all authoritative NIS Zones, Commands, and Enrollment Centres
 * from the official NIS Zones and Commands directory are seeded idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EnrollmentCenterSeeder)->run();
    }

    public function down(): void
    {
        // Reference data; no rollback destructive operation.
    }
};
