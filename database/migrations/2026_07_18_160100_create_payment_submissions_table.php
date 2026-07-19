<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('payment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('plan_id')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('NIO');
            $table->string('reference')->nullable();      // nº de transferencia / referencia
            $table->string('receipt_path')->nullable();    // archivo en disco privado
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();       // admin que revisó
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('payment_submissions');
    }
};
