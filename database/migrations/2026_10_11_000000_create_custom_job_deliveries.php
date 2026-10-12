<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_delivery_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('kind');
            $t->string('occurred_local', 16);
            $t->string('timezone');
            $t->dateTime('occurred_at');
            $t->uuid('user_id');
            $t->text('note');
            $t->timestamps();
            $t->unique(['order_id', 'request_id']);
        });
        Schema::create('custom_job_delivery_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('event_id')->constrained('custom_job_delivery_events')->restrictOnDelete();
            $t->foreignUuid('item_id')->constrained('custom_job_order_items')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('disposition')->nullable();
            $t->foreignUuid('source_line_id')->nullable()->constrained('custom_job_delivery_lines')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_delivery_lines');
        Schema::dropIfExists('custom_job_delivery_events');
    }
};
