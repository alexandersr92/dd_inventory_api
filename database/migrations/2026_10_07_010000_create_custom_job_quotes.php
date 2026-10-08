<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', fn (Blueprint $t) => $t->unsignedBigInteger('custom_job_quote_number')->default(0));
        Schema::create('custom_job_quotes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('store_id')->constrained()->restrictOnDelete();
            $t->uuid('client_id')->nullable();
            $t->string('client_name', 120);
            $t->string('number', 40);
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->uuid('issued_revision_id')->nullable();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->timestamps();
            $t->unique(['organization_id', 'request_id']);
            $t->unique(['organization_id', 'store_id', 'number']);
            $t->index(['organization_id', 'store_id', 'status']);
        });
        Schema::create('custom_job_quote_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id')->index();
            $t->foreignUuid('quote_id')->constrained('custom_job_quotes')->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->uuid('user_id');
            $t->json('snapshot');
            $t->json('commercial');
            $t->timestamps();
            $t->unique(['quote_id', 'number']);
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->foreignUuid('custom_job_quote_id')->nullable()->unique()->constrained('custom_job_quotes')->restrictOnDelete();
            $t->foreignUuid('custom_job_revision_id')->nullable()->constrained('custom_job_quote_revisions')->restrictOnDelete();
            // Public commercial lines only. Internal costs never enter a client document.
            $t->json('custom_job_commercial')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('custom_job_quote_id');
            $t->dropConstrainedForeignId('custom_job_revision_id');
            $t->dropColumn('custom_job_commercial');
        });
        Schema::dropIfExists('custom_job_quote_revisions');
        Schema::dropIfExists('custom_job_quotes');
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn('custom_job_quote_number'));
    }
};
