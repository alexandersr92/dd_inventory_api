<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('subscription_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();                 // DB-2026-0001
            $table->uuid('organization_id')->index();
            $table->uuid('payment_submission_id')->nullable()->index();
            $table->uuid('plan_id')->nullable();

            $table->string('concept');                          // "Plan Pro (6 meses) — Suscripción DipleBill"
            $table->date('period_start')->nullable();           // inicio de la licencia cubierta
            $table->date('period_end')->nullable();             // fin de la licencia cubierta

            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('NIO');
            $table->string('payment_method')->nullable();       // nombre del método (transferencia)
            $table->string('reference')->nullable();            // N° de transferencia

            // Snapshots inmutables: la factura no cambia aunque cambien la org, el plan o los datos de la empresa.
            $table->json('issuer');                             // datos de DipleBill al momento de emitir
            $table->json('customer');                           // datos del cliente al momento de emitir

            $table->timestamp('issued_at');
            $table->uuid('issued_by')->nullable();              // admin que aprobó
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('subscription_invoices');
    }
};
