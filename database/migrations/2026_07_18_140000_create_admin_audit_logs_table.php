<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_id')->nullable()->index();
            $table->string('admin_name')->nullable();
            $table->string('action')->index();          // ej. license.assign, plan.create, client.delete
            $table->string('target_type')->nullable();   // ej. organization, plan
            $table->string('target_id')->nullable();
            $table->string('description')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('admin_audit_logs');
    }
};
