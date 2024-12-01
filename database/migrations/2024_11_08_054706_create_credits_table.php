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
        Schema::create('credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('organization_id')->constrained();
            $table->foreignUuid('store_id')->constrained();
            $table->foreignUuid('client_id')->constrained();
            $table->foreignUuid('invoice_id')->constrained();
            $table->float('total')->default(0);
            $table->float('current')->default(0);
            $table->string('credit_status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
