<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('subdomain')->nullable()->unique()->after('website');
            $table->enum('tenancy_type', ['shared', 'dedicated'])->default('shared')->after('status');
            $table->string('db_database')->nullable()->after('tenancy_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['subdomain', 'tenancy_type', 'db_database']);
        });
    }
};
