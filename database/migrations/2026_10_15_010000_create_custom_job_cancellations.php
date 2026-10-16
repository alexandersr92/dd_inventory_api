<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('custom_job_orders', 'cancelled_at')) {
            Schema::table('custom_job_orders', function (Blueprint $t) {
                $t->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            });
        }
        if (! Schema::hasColumn('custom_job_orders', 'cancelled_by')) {
            Schema::table('custom_job_orders', function (Blueprint $t) {
                $t->uuid('cancelled_by')->nullable()->after('cancelled_at');
            });
        }
        if (! Schema::hasColumn('custom_job_orders', 'cancellation_reason')) {
            Schema::table('custom_job_orders', function (Blueprint $t) {
                $t->string('cancellation_reason', 1000)->nullable()->after('cancelled_by');
            });
        }
        if (! Schema::hasColumn('custom_job_work_orders', 'cancelled_at')) {
            Schema::table('custom_job_work_orders', function (Blueprint $t) {
                $t->timestamp('cancelled_at')->nullable()->after('user_id');
            });
        }
        if (! Schema::hasColumn('custom_job_work_orders', 'cancelled_by')) {
            Schema::table('custom_job_work_orders', function (Blueprint $t) {
                $t->uuid('cancelled_by')->nullable()->after('cancelled_at');
            });
        }
        if (Schema::hasTable('custom_job_cancellations')) {
            $indexes = collect(Schema::getIndexes('custom_job_cancellations'))->pluck('name');
            if (! $indexes->contains('custom_job_cancel_org_order_created_idx')) {
                Schema::table('custom_job_cancellations', fn (Blueprint $t) => $t->index(['organization_id', 'order_id', 'created_at'], 'custom_job_cancel_org_order_created_idx'));
            }

            return;
        }
        Schema::create('custom_job_cancellations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('order_id')->constrained('custom_job_orders')->restrictOnDelete();
            $t->foreignUuid('payment_id')->nullable()->constrained('custom_job_payments')->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained('users');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('operation', 24); // cancel, annul_payment
            $t->string('reason', 1000);
            $t->unsignedInteger('version_before');
            $t->unsignedInteger('version_after');
            $t->json('before_snapshot');
            $t->json('after_snapshot');
            $t->json('effects');
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->unique(['order_id', 'request_id']);
            $t->index(['organization_id', 'order_id', 'created_at'], 'custom_job_cancel_org_order_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_cancellations');
        Schema::table('custom_job_work_orders', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'cancelled_by']));
        Schema::table('custom_job_orders', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason']));
    }
};
