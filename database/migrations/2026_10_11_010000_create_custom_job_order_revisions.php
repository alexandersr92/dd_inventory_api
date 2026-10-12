<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_job_order_items', fn (Blueprint $t) => $t->boolean('active')->default(true));
        Schema::create('custom_job_order_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->uuid('user_id');
            $t->text('reason');
            $t->json('before');
            $t->json('after');
            $t->json('effects');
            $t->timestamps();
            $t->unique(['order_id', 'request_id']);
            $t->unique(['order_id', 'number']);
        });
        Schema::create('custom_job_order_surplus_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('revision_id')->constrained('custom_job_order_revisions')->restrictOnDelete();
            $t->foreignUuid('item_id')->constrained('custom_job_order_items')->restrictOnDelete();
            $t->integer('quantity');
            $t->string('disposition');
            $t->foreignUuid('source_event_id')->nullable()->constrained('custom_job_order_surplus_events')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::table('custom_job_production_events', fn (Blueprint $t) => $t->json('material_basis')->nullable());
    }

    public function down(): void
    {
        Schema::table('custom_job_production_events', fn (Blueprint $t) => $t->dropColumn('material_basis'));
        Schema::dropIfExists('custom_job_order_surplus_events');
        Schema::dropIfExists('custom_job_order_revisions');
        Schema::table('custom_job_order_items', fn (Blueprint $t) => $t->dropColumn('active'));
    }
};
