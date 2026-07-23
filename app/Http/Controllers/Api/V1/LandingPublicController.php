<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Models\Plan;

class LandingPublicController extends Controller
{
    public function getPublicContent()
    {
        $contents = LandingContent::all()->pluck('content', 'section_key')->toArray();
        return response()->json($contents);
    }

    /**
     * Planes que muestra la landing. Fuente única: los planes de licenciamiento
     * reales (tabla plans). Cada uno lleva precio mensual y anual; las features
     * se derivan de sus límites. Así lo anunciado y lo cobrado coinciden.
     */
    public function getPublicPlans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('price_monthly', 'asc')
            ->get()
            ->map(fn (Plan $plan) => [
                'name' => $plan->name,
                'price_monthly' => (float) $plan->price_monthly,
                'price_annual' => (float) $plan->price_annual,
                'currency' => $plan->currency,
                'is_featured' => (bool) $plan->is_featured,
                'features' => $this->featuresFor($plan),
            ]);

        return response()->json($plans);
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
