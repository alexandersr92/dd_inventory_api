<?php

namespace App\Observers;

use App\Models\InventoryDetail;
use App\Models\WooCommerceIntegration;
use App\Jobs\SyncStockToWooCommerce;

class InventoryDetailObserver implements \Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit
{
    /**
     * Handle the InventoryDetail "updated" event.
     */
    public function updated(InventoryDetail $inventoryDetail): void
    {
        // Solo actuar si la cantidad cambió
        if (!$inventoryDetail->wasChanged('quantity')) {
            return;
        }

        $this->sync($inventoryDetail);
    }

    public function sync(InventoryDetail $inventoryDetail): void
    {
        $inventory = $inventoryDetail->inventory;
        $product = $inventoryDetail->product;

        if (!$inventory || !$product || empty($product->sku)) {
            return;
        }

        $orgId = $inventory->organization_id;

        // Buscar si hay una integración activa para este inventario
        $integration = WooCommerceIntegration::where('organization_id', $orgId)
            ->where('inventory_id', $inventory->id)
            ->where('status', true)
            ->first();

        if ($integration) {
            SyncStockToWooCommerce::dispatch($integration, $product->sku, (float) $inventoryDetail->quantity);
        }
    }
}
