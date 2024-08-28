<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('module_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_modules');
    }
};
