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
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('replaces_invoice_id')->nullable()->comment('ID de la factura a la que esta factura reemplazó');
            $table->uuid('replaced_by_invoice_id')->nullable()->comment('ID de la nueva factura que reemplazó a esta cuando fue anulada');
            
            // Opcional: Foreign keys
            // $table->foreign('replaces_invoice_id')->references('id')->on('invoices')->onDelete('set null');
            // $table->foreign('replaced_by_invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['replaces_invoice_id', 'replaced_by_invoice_id']);
        });
    }
};
