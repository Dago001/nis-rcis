<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'emergency_contact_id_type')) {
                    $table->string('emergency_contact_id_type', 50)->nullable()->after('emergency_contact_lga');
                }
                if (! Schema::hasColumn($tableName, 'home_country_emergency_contact_id_type')) {
                    $table->string('home_country_emergency_contact_id_type', 50)->nullable()->after('home_country_emergency_contact_address');
                }
            });
        }

        if (Schema::hasTable('application_documents')) {
            Schema::table('application_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('application_documents', 'id_document_type')) {
                    $table->string('id_document_type', 50)->nullable()->after('type');
                }
                if (! Schema::hasColumn('application_documents', 'contact_type')) {
                    $table->string('contact_type', 20)->nullable()->after('id_document_type');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['applications', 'residence_cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = ['emergency_contact_id_type', 'home_country_emergency_contact_id_type'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('application_documents')) {
            Schema::table('application_documents', function (Blueprint $table) {
                foreach (['id_document_type', 'contact_type'] as $column) {
                    if (Schema::hasColumn('application_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
