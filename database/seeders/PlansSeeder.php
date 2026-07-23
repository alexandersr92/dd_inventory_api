<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes de ejemplo. Un plan es solo su TIER (límites); NO tiene duración fija.
 * Cada uno lleva dos precios —mensual y anual (con descuento)— y el cliente
 * elige el ciclo al pagar. Precios y límites son valores iniciales; ajústalos a
 * la oferta comercial real desde /admin/plans.
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'basico',
                'name' => 'Básico',
                'max_sellers' => 2,
                'max_stores' => 1,
                'max_monthly_invoices' => 500,
                'tenancy_type' => 'shared',
                'price_monthly' => 550,
                'price_annual' => 5500, // ~2 meses gratis
                'is_featured' => false,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'max_sellers' => 5,
                'max_stores' => 3,
                'max_monthly_invoices' => 3000,
                'tenancy_type' => 'shared',
                'price_monthly' => 1290,
                'price_annual' => 12900,
                'is_featured' => true,
            ],
            [
                'slug' => 'empresarial',
                'name' => 'Empresarial',
                'max_sellers' => null, // ilimitado
                'max_stores' => null,
                'max_monthly_invoices' => null,
                'tenancy_type' => 'dedicated',
                'price_monthly' => 2200,
                'price_annual' => 22000,
                'is_featured' => false,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
