<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('category_id')->nullable()->after('organization_id')->constrained('categories')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();

            $table->index('category_id');
            $table->index('brand_id');
        });

        // Migrate existing category_product entries to category_id
        if (Schema::hasTable('category_product')) {
            $relations = DB::table('category_product')->get();
            foreach ($relations as $rel) {
                DB::table('products')->where('id', $rel->product_id)->whereNull('category_id')->update([
                    'category_id' => $rel->category_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['category_id', 'brand_id']);
        });
    }
};
