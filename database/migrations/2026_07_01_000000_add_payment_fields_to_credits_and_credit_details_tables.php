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
        Schema::table('credits', function (Blueprint $table) {
            $table->string('credit_number')->nullable()->unique()->index()->after('invoice_id');
        });

        Schema::table('credit_details', function (Blueprint $table) {
            $table->string('payment_method')->default('CASH')->after('amount');
            $table->json('payment_metadata')->nullable()->after('payment_method');
            $table->uuid('cash_session_id')->nullable()->index()->after('payment_metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('credit_number');
        });

        Schema::table('credit_details', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_metadata', 'cash_session_id']);
        });
    }
};
