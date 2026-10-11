<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_material_operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('kind');
            $t->string('request_hash', 64);
            $t->uuid('user_id');
            $t->timestamps();
            $t->unique(['order_id', 'request_id']);
        });
        Schema::create('custom_job_material_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->foreignUuid('item_id')->constrained('custom_job_order_items')->restrictOnDelete();
            $t->unsignedInteger('material_index');
            $t->foreignUuid('inventory_detail_id')->constrained('inventory_details')->restrictOnDelete();
            $t->uuid('product_id');
            $t->string('name');
            $t->string('inventory_name');
            $t->string('base_unit');
            $t->decimal('unit_cost_nio', 20, 8);
            $t->timestamps();
            $t->unique(['item_id', 'material_index', 'inventory_detail_id'], 'custom_job_allocation_origin_unique');
        });
        Schema::create('custom_job_material_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('operation_id')->constrained('custom_job_material_operations')->restrictOnDelete();
            $t->foreignUuid('allocation_id')->constrained('custom_job_material_allocations')->restrictOnDelete();
            $t->string('kind');
            $t->decimal('quantity', 20, 6);
            $t->decimal('physical_quantity', 20, 6);
            $t->foreignUuid('source_event_id')->nullable()->constrained('custom_job_material_events')->restrictOnDelete();
            $t->foreignUuid('movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_material_events');
        Schema::dropIfExists('custom_job_material_allocations');
        Schema::dropIfExists('custom_job_material_operations');
    }
};
