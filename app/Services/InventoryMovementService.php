<?php

namespace App\Services;

use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryMovementService
{
    /**
     * Determine if a store allows negative stock.
     */
    public function allowsNegativeStock(string $storeId, string $orgId): bool
    {
        // Try to get store-specific setting first
        $value = Setting::where('organization_id', $orgId)
            ->where('type', 'store')
            ->where('entity_id', $storeId)
            ->whereIn('key', ['allow_no_stock_sales', 'allow_negative_stock'])
            ->value('value');

        if ($value === null) {
            // Fallback to global organization setting
            $value = Setting::where('organization_id', $orgId)
                ->where('type', 'global')
                ->whereIn('key', ['allow_no_stock_sales', 'allow_negative_stock'])
                ->value('value');
        }

        if ($value === null) {
            // Default to true (as currently hardcoded in InvoiceController)
            return true;
        }

        return $value === 'true' || $value === '1' || $value === true;
    }

    /**
     * Record an inventory movement and update stock.
     */
    public function recordMovement(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $detailId = $data['inventory_detail_id'];
            $type = $data['type'];
            $quantity = (float) $data['quantity'];
            $reason = $data['reason'] ?? null;
            $userId = $data['user_id'] ?? Auth::id();
            $sellerId = $data['seller_id'] ?? (Auth::user() ? Auth::user()->sellerId : null);
            $referenceId = $data['reference_id'] ?? null;
            $referenceType = $data['reference_type'] ?? null;

            // Get inventory detail locked for update
            $detail = InventoryDetail::where('id', $detailId)->lockForUpdate()->firstOrFail();
            $inventory = $detail->inventory;
            
            if (!$inventory) {
                throw new Exception('Inventario no encontrado para este detalle.');
            }

            $orgId = $inventory->organization_id;
            $storeId = $inventory->store_id;

            // Determine direction
            $direction = $this->getMovementDirection($type);
            $stockBefore = (float) $detail->quantity;

            // Calculate stock after
            if ($direction === 'in') {
                $stockAfter = $stockBefore + $quantity;
            } else {
                $stockAfter = $stockBefore - $quantity;

                // Validate negative stock constraints for EXIT movements
                if ($stockAfter < 0 && !$this->allowsNegativeStock($storeId, $orgId)) {
                    throw new Exception("El producto '{$detail->product->name}' no tiene stock suficiente para esta salida.");
                }
            }

            // Update quantity
            $detail->quantity = $stockAfter;
            $detail->save();

            // Create movement log
            return InventoryMovement::create([
                'organization_id' => $orgId,
                'inventory_id' => $inventory->id,
                'inventory_detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'store_id' => $storeId,
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'type' => $type,
                'direction' => $direction,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Determine direction from movement type.
     */
    public function getMovementDirection(string $type): string
    {
        $inTypes = ['return', 'adjustment_in', 'gift_in', 'manual_in', 'sale_cancel', 'purchase'];
        return in_array($type, $inTypes) ? 'in' : 'out';
    }

    /**
     * Reverse a movement and restore the stock.
     */
    public function reverseMovement(InventoryMovement $movement): InventoryMovement
    {
        return DB::transaction(function () use ($movement) {
            $detail = InventoryDetail::where('id', $movement->inventory_detail_id)->lockForUpdate()->firstOrFail();
            $orgId = $movement->organization_id;
            $storeId = $movement->store_id;

            $stockBefore = (float) $detail->quantity;
            $quantity = (float) $movement->quantity;

            // Reversing means doing the opposite direction
            if ($movement->direction === 'in') {
                // Was added, now subtract
                $stockAfter = $stockBefore - $quantity;

                if ($stockAfter < 0 && !$this->allowsNegativeStock($storeId, $orgId)) {
                    throw new Exception("No se puede reversar el movimiento. El stock resultante sería negativo.");
                }
            } else {
                // Was subtracted, now add
                $stockAfter = $stockBefore + $quantity;
            }

            $detail->quantity = $stockAfter;
            $detail->save();

            // Log the reversal movement
            return InventoryMovement::create([
                'organization_id' => $orgId,
                'inventory_id' => $movement->inventory_id,
                'inventory_detail_id' => $detail->id,
                'product_id' => $movement->product_id,
                'store_id' => $storeId,
                'user_id' => Auth::id(),
                'seller_id' => Auth::user() ? Auth::user()->sellerId : null,
                'type' => $movement->type . '_cancel',
                'direction' => $movement->direction === 'in' ? 'out' : 'in',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => "Reversión del movimiento ID: {$movement->id}",
                'reference_type' => 'InventoryMovement',
                'reference_id' => $movement->id,
            ]);
        });
    }
}
