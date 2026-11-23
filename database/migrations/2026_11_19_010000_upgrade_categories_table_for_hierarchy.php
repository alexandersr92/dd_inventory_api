<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->foreignUuid('parent_id')->nullable()->after('organization_id')->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('level')->default(1)->after('parent_id');
            $table->unsignedInteger('sort_order')->default(0)->after('level');
            $table->string('status')->default('active')->after('sort_order');
            
            $table->index(['organization_id', 'parent_id']);
            $table->index('status');
        });

        // Populate slug for existing categories
        $categories = DB::table('categories')->get();
        foreach ($categories as $cat) {
            DB::table('categories')->where('id', $cat->id)->update([
                'slug' => Str::slug($cat->name),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['slug', 'parent_id', 'level', 'sort_order', 'status']);
            $table->unique('name');
        });
    }
};
