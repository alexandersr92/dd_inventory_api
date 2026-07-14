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
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['organization_id', 'name', 'guard_name'], 'roles_org_name_guard_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_org_name_guard_unique');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
