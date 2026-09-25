<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NIS staff (officers). Roles: SuperAdmin, ApprovingOfficer,
        // IssuingOfficer, Inspector, Auditor — see App\Enums\StaffRole.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('fullname', 150);
            $table->string('service_number', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 30)->index();
            $table->string('command', 150)->default('National Processing Center');
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(true);
            $table->string('photo_path')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Public applicants (foreign nationals applying for a residence card).
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('surname', 100);
            $table->string('forenames', 150);
            $table->string('phone', 30);
            $table->string('nationality', 100)->nullable();
            $table->string('passport_number', 20)->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('applicant_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('applicant_password_reset_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('users');
    }
};
