<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite ANULAR un abono (pago de crédito) sin borrarlo: anulación suave y
 * auditable. Al anular se restaura la deuda del crédito y el abono deja de
 * contar en el arqueo de caja de sesiones abiertas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_details', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('note');
            $table->uuid('voided_by')->nullable()->after('voided_at'); // usuario que anuló
            $table->string('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('credit_details', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
};
