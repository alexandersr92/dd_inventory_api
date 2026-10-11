<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->unsignedBigInteger('custom_job_order_number')->default(0);
            $t->string('custom_job_timezone', 64)->default('America/Managua');
        });
        Schema::create('custom_job_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('store_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('quote_id')->unique()->constrained('custom_job_quotes')->restrictOnDelete();
            $t->foreignUuid('quote_revision_id')->constrained('custom_job_quote_revisions')->restrictOnDelete();
            $t->uuid('client_id')->nullable();
            $t->string('client_name', 120);
            $t->uuid('user_id');
            $t->string('number', 40);
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->dateTime('delivery_at'); // UTC, independent of the database/session timezone.
            $t->string('delivery_local', 16);
            $t->string('delivery_timezone', 64);
            $t->timestamp('confirmed_at')->nullable();
            $t->json('commercial');
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['organization_id', 'store_id', 'number']);
            $t->index(['organization_id', 'store_id', 'status']);
            $t->index(['organization_id', 'store_id', 'delivery_local']);
        });
        Schema::create('custom_job_order_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->unsignedInteger('position');
            $t->string('name', 120);
            $t->decimal('quantity', 20, 6);
            $t->json('commercial');
            $t->json('composition');
            $t->timestamps();
            $t->unique(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_order_items');
        Schema::dropIfExists('custom_job_orders');
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn(['custom_job_order_number', 'custom_job_timezone']));
    }
};
