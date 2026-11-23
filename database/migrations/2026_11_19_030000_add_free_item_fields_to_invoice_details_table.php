<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->string('item_type')->default('product')->after('invoice_id');
            $table->string('description')->nullable()->after('item_type');
            $table->uuid('product_id')->nullable()->change();
            $table->uuid('inventory_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'description']);
            $table->uuid('product_id')->nullable(false)->change();
            $table->uuid('inventory_id')->nullable(false)->change();
        });
    }
};
