<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('product_usage', 10)->default('sale')->index();
            $t->json('material_profile')->nullable();
            $t->decimal('cost', 20, 8)->change();
        });
        Schema::table('purchases', fn (Blueprint $t) => $t->uuid('supplier_id')->nullable()->change());
        Schema::table('purchase_details', function (Blueprint $t) {
            $t->string('product_usage', 10)->default('sale');
            $t->json('material_profile')->nullable();
            $t->decimal('stock_quantity', 20, 6)->nullable();
            $t->decimal('base_unit_cost', 20, 8)->nullable();
            $t->decimal('quantity', 20, 6)->default(0)->change();
            $t->decimal('cost', 20, 8)->default(0)->change();
        });
        DB::table('purchase_details')->update(['stock_quantity' => DB::raw('quantity'), 'base_unit_cost' => DB::raw('cost')]);
        foreach (['inventory_details' => ['quantity'], 'inventory_movements' => ['quantity', 'stock_before', 'stock_after'], 'invoice_details' => ['quantity'], 'purchases' => ['total_items']] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->decimal($column, 20, 6)->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Precision is deliberately retained: rollback must not truncate material fractions.
        Schema::table('purchase_details', fn (Blueprint $t) => $t->dropColumn(['product_usage', 'material_profile', 'stock_quantity', 'base_unit_cost']));
        Schema::table('products', function (Blueprint $t) {
            $t->dropIndex('products_product_usage_index');
            $t->dropColumn(['product_usage', 'material_profile']);
        });
    }
};
