<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('error_reports', function (Blueprint $table) {
            // Reportes que vienen de un cliente (dueño/admin de una organización).
            $table->uuid('organization_id')->nullable()->after('admin_name')->index();
            $table->string('organization_name')->nullable()->after('organization_id');
            $table->string('reporter_name')->nullable()->after('organization_name');
            $table->string('reporter_email')->nullable()->after('reporter_name');
            // origen: 'root' (panel super-admin) | 'tenant' (app del negocio)
            $table->string('source')->default('root')->after('reporter_email');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('error_reports', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'organization_name', 'reporter_name', 'reporter_email', 'source']);
        });
    }
};
