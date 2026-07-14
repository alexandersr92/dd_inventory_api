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
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->string('cash_register_name')->nullable();
            $table->double('opening_balance', 15, 2)->default(0);
            $table->double('expected_balance', 15, 2)->default(0);
            $table->double('actual_cash', 15, 2)->default(0);
            $table->double('difference', 15, 2)->default(0);
            $table->string('status')->default('open'); // open, closed
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->string('type'); // in, out
            $table->double('amount', 15, 2);
            $table->string('description');
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('cash_session_id')->nullable()->index()->constrained('cash_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['cash_session_id']);
            $table->dropColumn('cash_session_id');
        });

        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_sessions');
    }
};
