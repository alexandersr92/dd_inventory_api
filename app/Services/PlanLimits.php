<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Seller;
use App\Models\Store;

/**
 * Centraliza la verificación de los límites del plan de una organización:
 * vendedores, sucursales y facturación mensual. Si la organización no tiene
 * plan asignado (ej. durante el trial), los límites no aplican (null = ilimitado).
 */
class PlanLimits
{
    public function __construct(private Organization $organization)
    {
    }

    public static function for(Organization $organization): self
    {
        return new self($organization);
    }

    /** ¿Puede crear un vendedor más? */
    public function canAddSeller(): bool
    {
        $max = $this->organization->plan?->max_sellers;
        if ($max === null) {
            return true;
        }

        return $this->currentSellers() < $max;
    }

    /** ¿Puede crear una sucursal más? */
    public function canAddStore(): bool
    {
        $max = $this->organization->plan?->max_stores;
        if ($max === null) {
            return true;
        }

        return $this->currentStores() < $max;
    }

    /** ¿Puede emitir una factura más este mes? */
    public function canCreateInvoice(): bool
    {
        $max = $this->organization->plan?->max_monthly_invoices;
        if ($max === null) {
            return true;
        }

        return $this->invoicesThisMonth() < $max;
    }

    public function currentSellers(): int
    {
        return Seller::where('organization_id', $this->organization->id)->count();
    }

    public function currentStores(): int
    {
        return Store::where('organization_id', $this->organization->id)->count();
    }

    public function invoicesThisMonth(): int
    {
        return Invoice::where('organization_id', $this->organization->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /** Resumen de uso vs límites, para exponer en el panel o el frontend. */
    public function usage(): array
    {
        $plan = $this->organization->plan;

        return [
            'sellers' => [
                'used' => $this->currentSellers(),
                'limit' => $plan?->max_sellers,
            ],
            'stores' => [
                'used' => $this->currentStores(),
                'limit' => $plan?->max_stores,
            ],
            'monthly_invoices' => [
                'used' => $this->invoicesThisMonth(),
                'limit' => $plan?->max_monthly_invoices,
            ],
        ];
    }
}
