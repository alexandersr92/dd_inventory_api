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
        Schema::connection('central')->create('notification_events', function (Blueprint $table) {
            $table->string('id')->primary(); // Ej: 'client.registered', 'tenant.box_closed'
            $table->string('scope'); // 'global' o 'tenant'
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_channels'); // Canales por defecto en JSON, ej: ["mail", "database"]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('notification_events');
    }
};
