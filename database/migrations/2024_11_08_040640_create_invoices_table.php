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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            //$table->foreignId('seller_id')->constrained();
            $table->foreignUuid('organization_id')->constrained();
            $table->foreignUuid('store_id')->constrained();
            $table->foreignUuid('client_id')->nullable()->constrained();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_status')->default('paid');
            $table->string('invoice_date')->nullable();
            $table->string('invoice_note')->nullable();
            $table->string('client_name')->nullable();
            $table->float('total')->default(0);
            $table->float('discount')->default(0);
            $table->float('tax')->default(0);
            $table->float('grand_total')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
