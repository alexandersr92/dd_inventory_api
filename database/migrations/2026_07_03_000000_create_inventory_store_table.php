<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_store', function (Blueprint $table) {
            $table->foreignUuid('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Make store_id nullable on inventories table to migrate gradually
        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('store_id')->nullable()->change();
        });

        // Populate inventory_store with existing associations
        $inventories = DB::table('inventories')->whereNotNull('store_id')->get();
        foreach ($inventories as $inventory) {
            DB::table('inventory_store')->insert([
                'inventory_id' => $inventory->id,
                'store_id' => $inventory->store_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_store');

        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('store_id')->nullable(false)->change();
        });
    }
};
