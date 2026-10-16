<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_job_production_events', fn (Blueprint $table) => $table->index(['work_order_id', 'item_id', 'occurred_at'], 'custom_job_production_report_index'));
        Schema::table('custom_job_delivery_events', fn (Blueprint $table) => $table->index(['order_id', 'occurred_at'], 'custom_job_delivery_report_index'));
        Schema::table('custom_job_material_allocations', fn (Blueprint $table) => $table->index(['order_id', 'item_id'], 'custom_job_material_report_index'));
        Schema::table('custom_job_material_events', fn (Blueprint $table) => $table->index(['allocation_id', 'kind'], 'custom_job_material_event_report_index'));
        Schema::table('custom_job_payments', fn (Blueprint $table) => $table->index(['order_id', 'status', 'kind'], 'custom_job_payment_report_index'));
    }

    public function down(): void
    {
        Schema::table('custom_job_payments', fn (Blueprint $table) => $table->dropIndex('custom_job_payment_report_index'));
        Schema::table('custom_job_material_events', fn (Blueprint $table) => $table->dropIndex('custom_job_material_event_report_index'));
        Schema::table('custom_job_material_allocations', fn (Blueprint $table) => $table->dropIndex('custom_job_material_report_index'));
        Schema::table('custom_job_delivery_events', fn (Blueprint $table) => $table->dropIndex('custom_job_delivery_report_index'));
        Schema::table('custom_job_production_events', fn (Blueprint $table) => $table->dropIndex('custom_job_production_report_index'));
    }
};
