<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['custom_job_processes', 'custom_job_machines', 'custom_job_rates'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id')->index();
                $t->string('name', 120);
                $t->string('status')->default('active');
                $t->json('configuration');
                $t->timestamps();
                $t->index(['organization_id', 'status']);
            });
        }
        Schema::table('custom_job_rates', function (Blueprint $t) {
            $t->foreignUuid('process_id')->constrained('custom_job_processes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['custom_job_rates', 'custom_job_machines', 'custom_job_processes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
