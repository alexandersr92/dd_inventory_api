<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('error_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_id')->nullable();          // quién reportó (root)
            $table->string('admin_name')->nullable();
            $table->text('message');                       // qué pasó
            $table->string('page_url')->nullable();        // dónde ocurrió
            $table->text('user_agent')->nullable();
            $table->string('screenshot_path')->nullable(); // captura adjunta (disco privado)
            $table->enum('status', ['open', 'resolved'])->default('open')->index();
            $table->text('resolution_notes')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('error_reports');
    }
};
