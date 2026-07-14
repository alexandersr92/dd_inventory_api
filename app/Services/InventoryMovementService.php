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
        $inTypes = ['return', 'adjustment_in', 'gift_in', 'manual_in', 'sale_cancel', 'purchase', 'transfer_in'];
        return in_array($type, $inTypes) ? 'in' : 'out';
    }

    /**
     * Transfer stock from an origin inventory to a destination inventory (supports bulk).
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $originId = $data['origin_inventory_id'];
            $destId = $data['destination_inventory_id'];
            $reason = $data['reason'] ?? null;
            $userId = $data['user_id'] ?? Auth::id();
            $sellerId = $data['seller_id'] ?? (Auth::user() ? Auth::user()->sellerId : null);
            $productsList = $data['products'] ?? [];

            // Get inventories to verify existence
            $originInventory = \App\Models\Inventory::findOrFail($originId);
            $destInventory = \App\Models\Inventory::findOrFail($destId);

            $orgId = $originInventory->organization_id;
            $originStoreId = $originInventory->store_id;
            $destStoreId = $destInventory->store_id;

            $originLogs = [];
            $destLogs = [];

            foreach ($productsList as $item) {
                $productId = $item['product_id'];
                $quantity = (float) $item['quantity'];

                // 1. Get origin detail locked for update
                $originDetail = InventoryDetail::where('inventory_id', $originId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 2. Validate stock in origin
                $stockBeforeOrigin = (float) $originDetail->quantity;
                $stockAfterOrigin = $stockBeforeOrigin - $quantity;

                if ($stockAfterOrigin < 0 && !$this->allowsNegativeStock($originStoreId, $orgId)) {
                    throw new Exception("El inventario de origen no tiene stock suficiente para trasladar el producto '{$originDetail->product->name}'. Disponible: {$stockBeforeOrigin}");
                }

                // 3. Find or create destination detail locked for update
                $destDetail = InventoryDetail::where('inventory_id', $destId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (!$destDetail) {
                    $destDetail = InventoryDetail::create([
                        'inventory_id' => $destId,
                        'product_id' => $productId,
                        'quantity' => 0,
                        'status' => 'active',
                        'price' => $originDetail->price
                    ]);
                    $destDetail = InventoryDetail::where('id', $destDetail->id)->lockForUpdate()->firstOrFail();
                } else {
                    if ($destDetail->price === null || $destDetail->price === '' || $destDetail->price == 0) {
                        $destDetail->price = $originDetail->price;
                        $destDetail->save();
                    }
                }

                $stockBeforeDest = (float) $destDetail->quantity;
                $stockAfterDest = $stockBeforeDest + $quantity;

                // 4. Update quantities
                $originDetail->quantity = $stockAfterOrigin;
                $originDetail->save();

                $destDetail->quantity = $stockAfterDest;
                $destDetail->save();

                // 5. Create logs
                $originLogs[] = InventoryMovement::create([
                    'organization_id' => $orgId,
                    'inventory_id' => $originId,
                    'inventory_detail_id' => $originDetail->id,
                    'product_id' => $productId,
                    'store_id' => $originStoreId,
                    'user_id' => $userId,
                    'seller_id' => $sellerId,
                    'type' => 'transfer_out',
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'stock_before' => $stockBeforeOrigin,
                    'stock_after' => $stockAfterOrigin,
                    'reason' => $reason ?: "Traslado hacia el almacén '{$destInventory->name}'",
                    'reference_type' => 'App\Models\Inventory',
                    'reference_id' => $destId,
                ]);

                $destLogs[] = InventoryMovement::create([
                    'organization_id' => $orgId,
                    'inventory_id' => $destId,
                    'inventory_detail_id' => $destDetail->id,
                    'product_id' => $productId,
                    'store_id' => $destStoreId,
                    'user_id' => $userId,
                    'seller_id' => $sellerId,
                    'type' => 'transfer_in',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'stock_before' => $stockBeforeDest,
                    'stock_after' => $stockAfterDest,
                    'reason' => $reason ?: "Traslado recibido desde el almacén '{$originInventory->name}'",
                    'reference_type' => 'App\Models\Inventory',
                    'reference_id' => $originId,
                ]);
            }

            return [
                'origins' => $originLogs,
                'destinations' => $destLogs
            ];
        });
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
