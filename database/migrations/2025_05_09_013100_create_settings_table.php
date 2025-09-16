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
        Schema::create('settings', function (Blueprint $table) {
          $table->uuid('id')->primary();
          $table->uuid('organization_id');
          $table->string('type'); // 'global', 'store', 'seller', etc.
          $table->uuid('entity_id')->nullable(); // ID de tienda, vendedor, etc.
          $table->string('key');
          $table->text('value')->nullable();
          $table->timestamps();

          $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
          $table->unique(['organization_id', 'type', 'entity_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
