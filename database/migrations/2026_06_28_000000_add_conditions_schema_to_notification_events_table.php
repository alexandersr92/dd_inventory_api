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
        Schema::connection('central')->table('notification_events', function (Blueprint $table) {
            $table->json('conditions_schema')->nullable()->after('default_channels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('notification_events', function (Blueprint $table) {
            $table->dropColumn('conditions_schema');
        });
    }
};
