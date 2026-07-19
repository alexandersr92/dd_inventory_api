<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cash_sessions y cash_transactions no tenían organization_id, así que el
     * scope multitenant no las cubría y un tenant podía cerrar/editar la caja
     * de otro por ID. Se añade la columna y se rellena desde la tienda / sesión.
     */
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id')->index();
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id')->index();
        });

        // Backfill: la sesión hereda la organización de su tienda.
        DB::statement("
            UPDATE cash_sessions cs
            JOIN stores s ON s.id = cs.store_id
            SET cs.organization_id = s.organization_id
            WHERE cs.organization_id IS NULL
        ");

        // La transacción hereda la organización de su sesión.
        DB::statement("
            UPDATE cash_transactions ct
            JOIN cash_sessions cs ON cs.id = ct.cash_session_id
            SET ct.organization_id = cs.organization_id
            WHERE ct.organization_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};
