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
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->double('actual_usd', 15, 2)->default(0)->after('actual_cash');
            $table->double('expected_usd', 15, 2)->default(0)->after('actual_usd');
            $table->double('usd_exchange_rate', 15, 4)->default(0)->after('expected_usd');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->string('currency')->default('NIO')->after('amount');
            $table->foreignUuid('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete()->after('currency');
            $table->uuid('reference_id')->nullable()->after('expense_category_id');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete()->after('reference_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->double('paid_in_usd', 15, 2)->default(0)->after('grand_total');
            $table->double('paid_in_nio', 15, 2)->default(0)->after('paid_in_usd');
            $table->double('exchange_rate', 15, 4)->default(0)->after('paid_in_nio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_in_usd', 'paid_in_nio', 'exchange_rate']);
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['currency', 'expense_category_id', 'reference_id', 'user_id']);
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn(['actual_usd', 'expected_usd', 'usd_exchange_rate']);
        });
    }
};
