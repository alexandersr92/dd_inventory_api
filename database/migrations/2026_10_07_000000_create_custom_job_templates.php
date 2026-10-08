<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('status')->default('active');
            // Structured article components reuse the versioned calculation input; no stored prices.
            $t->unsignedSmallInteger('schema_version')->default(1);
            $t->json('composition');
            $t->timestamps();
            $t->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_job_templates');
    }
};
