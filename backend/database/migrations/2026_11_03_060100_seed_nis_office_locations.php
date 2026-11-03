<?php

use Database\Seeders\NisOfficeLocationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new NisOfficeLocationSeeder)->run();
    }

    public function down(): void
    {
        // Reference data; no rollback destructive operation.
    }
};
