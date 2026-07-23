<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desacopla el tier del plan de su duración. Antes cada plan tenía una duración
 * fija (3/6/12 meses) y un precio único. Ahora un plan es solo su tier (límites)
 * con DOS precios —mensual y anual—, y el cliente elige el ciclo al pagar; la
 * duración de la licencia sale del ciclo (mensual = +1 mes, anual = +12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_monthly', 10, 2)->default(0)->after('tenancy_type');
            $table->decimal('price_annual', 10, 2)->default(0)->after('price_monthly');
            $table->boolean('is_featured')->default(false)->after('is_active');
        });

        // Dato legado: el precio único pasa a mensual; el anual arranca en x10
        // (~2 meses gratis) como valor inicial, editable desde el admin.
        DB::table('plans')->update([
            'price_monthly' => DB::raw('price'),
            'price_annual' => DB::raw('price * 10'),
        ]);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'price']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly')->after('plan_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Ciclo con el que la org contrató por última vez (renovación + MRR).
            $table->enum('billing_cycle', ['monthly', 'annual'])->nullable()->after('license_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(1)->after('slug');
            $table->decimal('price', 10, 2)->default(0)->after('tenancy_type');
        });

        DB::table('plans')->update(['price' => DB::raw('price_monthly')]);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_monthly', 'price_annual', 'is_featured']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};
