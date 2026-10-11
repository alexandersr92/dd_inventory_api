<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_work_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->unique()->constrained('custom_job_orders')->restrictOnDelete();
            $t->uuid('user_id');
            $t->timestamps();
        });
        Schema::create('custom_job_production_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('work_order_id')->constrained('custom_job_work_orders')->restrictOnDelete();
            $t->foreignUuid('item_id')->constrained('custom_job_order_items')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('kind');
            $t->decimal('produced', 20, 6);
            $t->decimal('discarded', 20, 6);
            $t->string('occurred_local', 16);
            $t->string('timezone');
            $t->dateTime('occurred_at');
            $t->uuid('user_id');
            $t->text('note');
            $t->foreignUuid('corrects_event_id')->nullable()->unique()->constrained('custom_job_production_events')->restrictOnDelete();
            $t->foreignUuid('material_operation_id')->constrained('custom_job_material_operations')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['work_order_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_production_events');
        Schema::dropIfExists('custom_job_work_orders');
    }
};
