<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\TenantManager;

return new class extends Migration
{
    /**
     * Determines whether the migration is running on the central or shared database context.
     * Product releases is a central table and must only be created/dropped in central/shared.
     */
    public function isCentralOrShared(): bool
    {
        if (TenantManager::getTenant()?->tenancy_type === 'dedicated') {
            return false;
        }

        $schemaConnection = Schema::getConnection();
        $currentConnectionName = $schemaConnection->getName();

        if ($currentConnectionName === 'central') {
            return true;
        }

        $currentDb = $schemaConnection->getDatabaseName();
        $centralDb = config('database.connections.central.database');
        $defaultSharedDb = env('DB_DATABASE');

        if ($currentDb && ($currentDb === $centralDb || ($defaultSharedDb && $currentDb === $defaultSharedDb))) {
            return true;
        }

        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->isCentralOrShared()) {
            return;
        }

        if (Schema::connection('central')->hasTable('product_releases')) {
            return;
        }

        Schema::connection('central')->create('product_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version', 50);
            $table->string('title', 255);
            $table->text('body');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->isCentralOrShared()) {
            return;
        }

        Schema::connection('central')->dropIfExists('product_releases');
    }
};
