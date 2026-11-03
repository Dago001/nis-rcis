<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurable Register of Approved NIS Office Locations for Physical Geofencing
        if (! Schema::hasTable('nis_office_locations')) {
            Schema::create('nis_office_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 50)->unique();
                $table->foreignId('zone_id')->nullable()->constrained('nis_zones')->nullOnDelete();
                $table->foreignId('command_id')->nullable()->constrained('nis_commands')->nullOnDelete();
                $table->foreignId('enrollment_center_id')->nullable()->constrained('enrollment_centers')->nullOnDelete();
                $table->string('state', 80)->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('radius_meters')->default(500);
                $table->boolean('is_active')->default(true);
                $table->boolean('enforce_biometrics')->default(true);
                $table->boolean('enforce_card_receipt')->default(true);
                $table->boolean('enforce_card_issuance')->default(true);
                $table->timestamps();

                $table->index(['zone_id', 'is_active']);
                $table->index(['command_id', 'is_active']);
            });
        }

        // 2. Card Distribution Table (Headquarters to Zones & Commands)
        if (! Schema::hasTable('card_distributions')) {
            Schema::create('card_distributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('card_id')->constrained('residence_cards')->cascadeOnDelete();
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->string('card_number', 50)->index();
                $table->string('application_number', 50)->index();
                $table->foreignId('destination_zone_id')->nullable()->constrained('nis_zones')->nullOnDelete();
                $table->foreignId('destination_command_id')->nullable()->constrained('nis_commands')->nullOnDelete();
                $table->foreignId('destination_center_id')->nullable()->constrained('enrollment_centers')->nullOnDelete();
                $table->string('dispatch_reference', 60)->nullable()->index();
                $table->string('status', 40)->default('AWAITING_DISPATCH')->index(); // AWAITING_DISPATCH, DISPATCHED, RECEIVED, AVAILABLE_FOR_ISSUANCE, ISSUED
                $table->string('courier_service', 100)->nullable();
                $table->string('waybill_number', 100)->nullable();
                $table->text('dispatch_notes')->nullable();
                $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('dispatched_at')->nullable();
                $table->text('receipt_notes')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('received_at')->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('issued_at')->nullable();
                $table->boolean('geofence_verified_receipt')->default(false);
                $table->boolean('geofence_verified_issuance')->default(false);
                $table->decimal('receipt_latitude', 10, 7)->nullable();
                $table->decimal('receipt_longitude', 10, 7)->nullable();
                $table->decimal('issuance_latitude', 10, 7)->nullable();
                $table->decimal('issuance_longitude', 10, 7)->nullable();
                $table->timestamps();

                $table->index(['destination_zone_id', 'status']);
                $table->index(['destination_command_id', 'status']);
            });
        }

        // 3. User table extensions for Headquarters unit and Geofence exception
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'headquarters_unit')) {
                    $table->string('headquarters_unit', 100)->nullable()->after('command');
                }
                if (! Schema::hasColumn('users', 'geofence_exempt')) {
                    $table->boolean('geofence_exempt')->default(false)->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_intersect(['headquarters_unit', 'geofence_exempt'], Schema::getColumnListing('users'));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('card_distributions');
        Schema::dropIfExists('nis_office_locations');
    }
};
