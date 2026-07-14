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
        Schema::table('organization_licenses', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE organization_licenses MODIFY COLUMN type ENUM('add', 'replace', 'lifetime', 'revoke') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_licenses', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE organization_licenses MODIFY COLUMN type ENUM('add', 'replace', 'lifetime') NOT NULL");
        });
    }
};
