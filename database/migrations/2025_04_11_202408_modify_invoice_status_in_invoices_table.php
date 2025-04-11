<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_status')->nullable(false)->default(null)->change();
            $table->string('invoice_type')->nullable(false)->default(null)->change(); // ← esta línea es nueva
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_status')->default('Completed')->change();
            $table->string('invoice_type')->default('Contado')->change(); // ← revertir si antes tenía esto
        });
    }
};
