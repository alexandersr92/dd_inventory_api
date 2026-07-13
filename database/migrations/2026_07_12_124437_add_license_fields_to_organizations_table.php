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
            $table->boolean('is_lifetime')->default(false)->after('status');
            $table->dateTime('license_expires_at')->nullable()->after('is_lifetime');
            $table->text('support_message')->nullable()->after('license_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['is_lifetime', 'license_expires_at', 'support_message']);
        });
    }
};
