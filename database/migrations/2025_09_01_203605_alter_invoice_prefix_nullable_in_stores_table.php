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
        // Eliminar la columna actual
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('invoice_prefix');
        });

        // Volver a crearla como nullable
        Schema::table('stores', function (Blueprint $table) {
            $table->string('invoice_prefix')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la versión nullable
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('invoice_prefix');
        });

        // Volver a crearla como NOT NULL con default vacío
        Schema::table('stores', function (Blueprint $table) {
            $table->string('invoice_prefix')->default('');
        });
    }
};