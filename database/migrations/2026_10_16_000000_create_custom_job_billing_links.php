<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('custom_job_order_id')->nullable()->after('custom_job_revision_id')
                ->unique('invoices_custom_job_order_unique')
                ->constrained('custom_job_orders')->restrictOnDelete();
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['inventory_id']);
            $table->uuid('product_id')->nullable()->change();
            $table->uuid('inventory_id')->nullable()->change();
            $table->string('line_type', 32)->default('product')->after('invoice_id');
            $table->foreignUuid('custom_job_order_item_id')->nullable()->after('line_type')
                ->constrained('custom_job_order_items')->restrictOnDelete();
            $table->string('custom_job_description', 255)->nullable()->after('custom_job_order_item_id');
            $table->json('custom_job_snapshot')->nullable()->after('custom_job_description');
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('inventory_id')->references('id')->on('inventories')->restrictOnDelete();
        });

        Schema::table('custom_job_payments', function (Blueprint $table) {
            $table->foreignUuid('applied_invoice_id')->nullable()->after('cash_session_id')
                ->constrained('invoices')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('applied_invoice_id');
            $table->index(['order_id', 'applied_invoice_id'], 'custom_job_payments_invoice_index');
        });
    }

    public function down(): void
    {
        Schema::table('custom_job_payments', function (Blueprint $table) {
            $table->dropIndex('custom_job_payments_invoice_index');
            $table->dropConstrainedForeignId('applied_invoice_id');
            $table->dropColumn('applied_at');
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['custom_job_order_item_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropColumn(['line_type', 'custom_job_order_item_id', 'custom_job_description', 'custom_job_snapshot']);
            $table->uuid('product_id')->nullable(false)->change();
            $table->uuid('inventory_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('inventory_id')->references('id')->on('inventories')->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_job_order_id');
        });
    }
};
