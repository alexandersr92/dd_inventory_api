<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Borrar relación con store
            if (Schema::hasColumn('sellers', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }

            // Agregar hash del PIN
            if (!Schema::hasColumn('sellers', 'pin_hash')) {
                $table->string('pin_hash')->nullable()->after('code');
            }

            // Soft deletes
            if (!Schema::hasColumn('sellers', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Restaurar store_id
            if (!Schema::hasColumn('sellers', 'store_id')) {
                $table->foreignUuid('store_id')->nullable()->constrained()->onDelete('cascade');
            }

            if (Schema::hasColumn('sellers', 'pin_hash')) {
                $table->dropColumn('pin_hash');
            }

            if (Schema::hasColumn('sellers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};