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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->foreignUuid('inventory_id')->constrained();
            $table->float('quantity')->default(0);
            $table->float('price')->default(0);
            $table->float('total')->default(0);
            $table->float('discount')->default(0);
            $table->float('tax')->default(0);
            $table->float('grand_total')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
