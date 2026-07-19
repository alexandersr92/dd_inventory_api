<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes de ejemplo. Precios y límites son valores iniciales editables desde
 * el panel de administración; ajústalos a la oferta comercial real.
 *
 * La estructura combina duración (3/6/12 meses) con límites de vendedores,
 * sucursales y facturación mensual. La facturación mensual alta implica base
 * de datos dedicada.
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'basico-3m',
                'name' => 'Básico (3 meses)',
                'duration_months' => 3,
                'max_sellers' => 2,
                'max_stores' => 1,
                'max_monthly_invoices' => 500,
                'tenancy_type' => 'shared',
                'price' => 900,
            ],
            [
                'slug' => 'pro-6m',
                'name' => 'Pro (6 meses)',
                'duration_months' => 6,
                'max_sellers' => 5,
                'max_stores' => 3,
                'max_monthly_invoices' => 3000,
                'tenancy_type' => 'shared',
                'price' => 3200,
            ],
            [
                'slug' => 'empresarial-12m',
                'name' => 'Empresarial (12 meses)',
                'duration_months' => 12,
                'max_sellers' => null, // ilimitado
                'max_stores' => null,
                'max_monthly_invoices' => null,
                'tenancy_type' => 'dedicated',
                'price' => 12000,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
