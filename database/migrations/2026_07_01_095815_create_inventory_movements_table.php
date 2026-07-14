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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained();
            $table->foreignUuid('inventory_id')->constrained();
            $table->foreignUuid('inventory_detail_id')->constrained('inventory_details');
            $table->foreignUuid('product_id')->constrained();
            $table->foreignUuid('store_id')->constrained();
            $table->foreignUuid('user_id')->nullable()->constrained();
            $table->foreignUuid('seller_id')->nullable()->constrained();
            $table->string('type');
            $table->enum('direction', ['in', 'out']);
            $table->float('quantity');
            $table->float('stock_before');
            $table->float('stock_after');
            $table->text('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->timestamps();

            // Indexes for fast querying
            $table->index(['organization_id', 'inventory_id']);
            $table->index('product_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
