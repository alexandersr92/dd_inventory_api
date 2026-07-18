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
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('offline_reference')->nullable()->unique()->after('source');
            $table->boolean('is_offline')->default(false)->after('offline_reference');
            $table->string('offline_number', 30)->nullable()->after('is_offline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['offline_reference']);
            $table->dropColumn(['offline_reference', 'is_offline', 'offline_number']);
        });
    }
};
