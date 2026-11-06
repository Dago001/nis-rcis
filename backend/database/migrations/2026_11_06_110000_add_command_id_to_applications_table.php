<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (! Schema::hasColumn('applications', 'command_id')) {
                    $table->foreignId('command_id')
                        ->nullable()
                        ->after('enrollment_center_id')
                        ->constrained('nis_commands')
                        ->nullOnDelete();
                }
            });

            // Safely backfill command_id from enrollment_centers.command_id
            try {
                DB::statement('
                    UPDATE applications 
                    INNER JOIN enrollment_centers ON applications.enrollment_center_id = enrollment_centers.id
                    SET applications.command_id = enrollment_centers.command_id
                    WHERE applications.command_id IS NULL AND enrollment_centers.command_id IS NOT NULL
                ');
            } catch (\Throwable $e) {
                // Fallback for SQLite / PostgreSQL syntax
                try {
                    DB::statement('
                        UPDATE applications 
                        SET command_id = (
                            SELECT command_id FROM enrollment_centers 
                            WHERE enrollment_centers.id = applications.enrollment_center_id
                        )
                        WHERE command_id IS NULL
                    ');
                } catch (\Throwable $e2) {
                    // Log or ignore if table is empty
                }
            }

            // Index for fast command-level queries
            Schema::table('applications', function (Blueprint $table) {
                try {
                    $table->index(['command_id', 'status'], 'apps_command_status_idx');
                } catch (\Throwable $e) {
                    // Index already exists
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'command_id')) {
            Schema::table('applications', function (Blueprint $table) {
                try {
                    $table->dropIndex('apps_command_status_idx');
                } catch (\Throwable) {}
                $table->dropConstrainedForeignId('command_id');
            });
        }
    }
};
