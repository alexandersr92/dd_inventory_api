<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_store', function (Blueprint $table) {
         

            $table->foreignUuid('organization_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('seller_id')->constrained('sellers')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'seller_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_store');
    }
};