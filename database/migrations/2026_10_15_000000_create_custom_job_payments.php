<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders');
            $t->foreignUuid('source_payment_id')->nullable()->constrained('custom_job_payments');
            $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions');
            $t->foreignUuid('user_id')->constrained('users');
            $t->string('kind', 12); // payment, refund; cancellation compensation belongs to plan 13.
            $t->string('status', 16)->default('active');
            $t->unsignedInteger('sequence');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('currency', 3);
            $t->decimal('amount', 20, 2);
            $t->decimal('amount_nio', 20, 2);
            $t->decimal('exchange_rate', 20, 8);
            $t->string('payment_method', 12);
            $t->json('payment_metadata');
            $t->string('reason', 1000);
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->unique(['order_id', 'request_id']);
            $t->unique(['order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_payments');
    }
};
