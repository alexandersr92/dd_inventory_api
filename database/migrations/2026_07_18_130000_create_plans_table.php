<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();

            // Duración de la licencia en meses (3, 6, 12).
            $table->unsignedSmallInteger('duration_months');

            // Límites. null = ilimitado.
            $table->unsignedInteger('max_sellers')->nullable();
            $table->unsignedInteger('max_stores')->nullable();
            $table->unsignedInteger('max_monthly_invoices')->nullable();

            // Un plan grande puede correr en base de datos dedicada.
            $table->enum('tenancy_type', ['shared', 'dedicated'])->default('shared');

            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('NIO');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('central')->table('organizations', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable()->after('tenancy_type');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('organizations', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
        Schema::connection('central')->dropIfExists('plans');
    }
};
