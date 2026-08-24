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
        Schema::create('terminal_magic_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUuid('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name')->default('Terminal POS');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('claimed_ip', 45)->nullable();
            $table->text('claimed_user_agent')->nullable();
            $table->timestamps();

            $table->index(['token_hash', 'used_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_magic_links');
    }
};
