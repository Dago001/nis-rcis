<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nis_zones')) {
            Schema::create('nis_zones', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('headquarters', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nis_commands')) {
            Schema::create('nis_commands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->constrained('nis_zones')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name', 150);
                $table->string('state', 80);
                $table->string('location', 255);
                $table->boolean('is_biometric_enabled')->default(true);
                $table->boolean('is_booking_enabled')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['zone_id', 'is_active']);
            });
        }

        if (Schema::hasTable('enrollment_centers')) {
            Schema::table('enrollment_centers', function (Blueprint $table) {
                if (! Schema::hasColumn('enrollment_centers', 'zone_id')) {
                    $table->foreignId('zone_id')->nullable()->after('address')->constrained('nis_zones')->nullOnDelete();
                }
                if (! Schema::hasColumn('enrollment_centers', 'command_id')) {
                    $table->foreignId('command_id')->nullable()->after('zone_id')->constrained('nis_commands')->nullOnDelete();
                }
                if (! Schema::hasColumn('enrollment_centers', 'zone_code')) {
                    $table->string('zone_code', 20)->nullable()->after('command_id');
                }
                if (! Schema::hasColumn('enrollment_centers', 'zone_name')) {
                    $table->string('zone_name', 100)->nullable()->after('zone_code');
                }
                if (! Schema::hasColumn('enrollment_centers', 'command_name')) {
                    $table->string('command_name', 150)->nullable()->after('zone_name');
                }
                if (! Schema::hasColumn('enrollment_centers', 'is_biometric_enabled')) {
                    $table->boolean('is_biometric_enabled')->default(true)->after('is_active');
                }
                if (! Schema::hasColumn('enrollment_centers', 'is_booking_enabled')) {
                    $table->boolean('is_booking_enabled')->default(true)->after('is_biometric_enabled');
                }
            });
        }

        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (! Schema::hasColumn('applications', 'actual_enrollment_center_id')) {
                    $table->foreignId('actual_enrollment_center_id')->nullable()->after('enrollment_center_id')->constrained('enrollment_centers')->nullOnDelete();
                }
                if (! Schema::hasColumn('applications', 'actual_enrollment_command')) {
                    $table->string('actual_enrollment_command', 150)->nullable()->after('actual_enrollment_center_id');
                }
                if (! Schema::hasColumn('applications', 'actual_enrollment_zone')) {
                    $table->string('actual_enrollment_zone', 100)->nullable()->after('actual_enrollment_command');
                }
                if (! Schema::hasColumn('applications', 'actual_enrollment_state')) {
                    $table->string('actual_enrollment_state', 80)->nullable()->after('actual_enrollment_zone');
                }
            });
        }

        if (Schema::hasTable('residence_cards')) {
            Schema::table('residence_cards', function (Blueprint $table) {
                if (! Schema::hasColumn('residence_cards', 'enrollment_zone')) {
                    $table->string('enrollment_zone', 100)->nullable()->after('issued_at');
                }
                if (! Schema::hasColumn('residence_cards', 'enrollment_command')) {
                    $table->string('enrollment_command', 150)->nullable()->after('enrollment_zone');
                }
                if (! Schema::hasColumn('residence_cards', 'actual_enrollment_center_id')) {
                    $table->foreignId('actual_enrollment_center_id')->nullable()->after('enrollment_command')->constrained('enrollment_centers')->nullOnDelete();
                }
                if (! Schema::hasColumn('residence_cards', 'production_location')) {
                    $table->string('production_location', 150)->default('NIS Service Headquarters')->after('actual_enrollment_center_id');
                }
            });
        }

        if (Schema::hasTable('card_print_jobs')) {
            Schema::table('card_print_jobs', function (Blueprint $table) {
                if (! Schema::hasColumn('card_print_jobs', 'production_location')) {
                    $table->string('production_location', 150)->default('NIS Service Headquarters')->after('outcome');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'zone_id')) {
                    $table->foreignId('zone_id')->nullable()->after('command')->constrained('nis_zones')->nullOnDelete();
                }
                if (! Schema::hasColumn('users', 'command_id')) {
                    $table->foreignId('command_id')->nullable()->after('zone_id')->constrained('nis_commands')->nullOnDelete();
                }
                if (! Schema::hasColumn('users', 'enrollment_center_id')) {
                    $table->foreignId('enrollment_center_id')->nullable()->after('command_id')->constrained('enrollment_centers')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'enrollment_center_id')) {
                    $table->dropConstrainedForeignId('enrollment_center_id');
                }
                if (Schema::hasColumn('users', 'command_id')) {
                    $table->dropConstrainedForeignId('command_id');
                }
                if (Schema::hasColumn('users', 'zone_id')) {
                    $table->dropConstrainedForeignId('zone_id');
                }
            });
        }

        if (Schema::hasTable('card_print_jobs')) {
            Schema::table('card_print_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('card_print_jobs', 'production_location')) {
                    $table->dropColumn('production_location');
                }
            });
        }

        if (Schema::hasTable('residence_cards')) {
            Schema::table('residence_cards', function (Blueprint $table) {
                if (Schema::hasColumn('residence_cards', 'actual_enrollment_center_id')) {
                    $table->dropConstrainedForeignId('actual_enrollment_center_id');
                }
                $table->dropColumn(array_intersect(
                    ['enrollment_zone', 'enrollment_command', 'production_location'],
                    Schema::getColumnListing('residence_cards')
                ));
            });
        }

        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (Schema::hasColumn('applications', 'actual_enrollment_center_id')) {
                    $table->dropConstrainedForeignId('actual_enrollment_center_id');
                }
                $table->dropColumn(array_intersect(
                    ['actual_enrollment_command', 'actual_enrollment_zone', 'actual_enrollment_state'],
                    Schema::getColumnListing('applications')
                ));
            });
        }

        if (Schema::hasTable('enrollment_centers')) {
            Schema::table('enrollment_centers', function (Blueprint $table) {
                if (Schema::hasColumn('enrollment_centers', 'command_id')) {
                    $table->dropConstrainedForeignId('command_id');
                }
                if (Schema::hasColumn('enrollment_centers', 'zone_id')) {
                    $table->dropConstrainedForeignId('zone_id');
                }
                $table->dropColumn(array_intersect(
                    ['zone_code', 'zone_name', 'command_name', 'is_biometric_enabled', 'is_booking_enabled'],
                    Schema::getColumnListing('enrollment_centers')
                ));
            });
        }

        Schema::dropIfExists('nis_commands');
        Schema::dropIfExists('nis_zones');
    }
};
