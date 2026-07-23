<?php

namespace Database\Seeders;

use App\Models\LandingPlan;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Siembra los planes que muestra la landing (landing_plans) DERIVÁNDOLOS de los
 * planes reales de licenciamiento (plans). Así lo que se anuncia y lo que se
 * cobra parten de la misma fuente y no se desalinean. Las features se generan a
 * partir de los límites del plan; el texto puede refinarse luego en /admin/landing.
 *
 * Idempotente (updateOrCreate por nombre). Ejecutar en producción con:
 *   php artisan db:seed --class=Database\\Seeders\\LandingPlanSeeder
 */
class LandingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        if ($plans->isEmpty()) {
            return;
        }

        $lastIndex = $plans->count() - 1;

        foreach ($plans->values() as $index => $plan) {
            LandingPlan::updateOrCreate(
                ['name' => $this->marketingName($plan->name)],
                [
                    'price' => (int) $plan->price,
                    'period' => $this->periodLabel((int) $plan->duration_months),
                    'discount' => 0,
                    'features' => $this->featuresFor($plan),
                    // Destacar el plan intermedio (o el único si solo hay uno).
                    'is_featured' => $plans->count() > 1 && $index === (int) floor($lastIndex / 2),
                    'status' => 'active',
                ]
            );
        }
    }

    /** "Básico (3 meses)" -> "Básico" (el período se muestra aparte). */
    private function marketingName(string $name): string
    {
        return trim(preg_replace('/\s*\(.*?\)\s*/', '', $name)) ?: $name;
    }

    private function periodLabel(int $months): string
    {
        if ($months <= 1) {
            return 'por mes';
        }

        return "por {$months} meses";
    }

    private function featuresFor(Plan $plan): array
    {
        $features = [];

        $features[] = $plan->max_stores === null
            ? 'Sucursales ilimitadas'
            : ($plan->max_stores == 1 ? '1 sucursal' : "Hasta {$plan->max_stores} sucursales");

        $features[] = $plan->max_sellers === null
            ? 'Vendedores ilimitados'
            : "Hasta {$plan->max_sellers} vendedores";

        $features[] = $plan->max_monthly_invoices === null
            ? 'Facturación ilimitada'
            : 'Hasta ' . number_format($plan->max_monthly_invoices) . ' facturas por mes';

        $features[] = 'Punto de venta, inventario y reportes';
        $features[] = 'Control de caja y créditos';

        if ($plan->tenancy_type === 'dedicated') {
            $features[] = 'Base de datos dedicada';
            $features[] = 'Soporte prioritario';
        }

        return $features;
    }
}
