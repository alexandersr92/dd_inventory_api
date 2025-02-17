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
        
            Schema::create('purchases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained();
                $table->foreignUuid('organization_id')->constrained();
                $table->foreignUuid('store_id')->constrained();
                $table->foreignUuid('supplier_id')->constrained()->nullable();
                $table->foreignUuid('inventory_id')->constrained();
                $table->float('total')->default(0);
                $table->date('purchase_date');
                $table->string('purchase_note')->nullable();
                $table->float('total_items')->default(0);
                $table->enum('status', [ 'completed', 'cancelled'])->default('completed');
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};


