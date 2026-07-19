<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('payment_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                       // "Transferencia BAC"
            $table->string('driver')->default('transfer'); // transfer | stripe | polar | local_*
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('mode', ['test', 'live'])->default('live');
            // Instrucciones visibles al cliente (datos de cuenta para transferencia).
            $table->text('instructions')->nullable();
            // Si requiere que el cliente suba un comprobante para validación manual.
            $table->boolean('supports_receipt')->default(true);
            // Credenciales de pasarelas automáticas (cifrado a nivel de aplicación).
            $table->text('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('payment_providers');
    }
};
