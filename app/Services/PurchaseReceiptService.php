<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\Purchases;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptService
{
    public function save(array $data, ?Purchases $purchase = null): Purchases
    {
        return DB::transaction(function () use ($data, $purchase) {
            Validator::make($data, [
                'store_id' => 'required|uuid', 'inventory_id' => 'required|uuid',
                'supplier_id' => 'nullable|uuid', 'purchase_date' => 'required|date',
                'invoice_number' => 'nullable|string|max:255', 'purchase_note' => 'nullable|string',
                'products' => 'required|array|min:1', 'products.*.product_id' => 'nullable|uuid',
                'products.*.sku' => 'required|string|max:255', 'products.*.product_name' => 'required|string|max:255',
                'products.*.quantity' => 'required|numeric|gt:0|max:99999999999999|decimal:0,6',
                'products.*.cost' => 'required|numeric|min:0|max:999999999999',
                'products.*.price' => 'required|numeric|min:0',
                'products.*.product_usage' => 'sometimes|required|in:sale,supply,both',
                'products.*.material_profile' => 'sometimes|nullable|array',
                'products.*.unit_of_measure' => 'sometimes|required|string|max:255',
                'products.*.update_product_usage' => 'sometimes|boolean',
            ])->validate();
            $orgId = Auth::user()->organization_id;
            $store = Store::findOrFail($data['store_id']);
            $inventory = Inventory::findOrFail($data['inventory_id']);
            $allowed = Auth::user()->allowedStoreIds();
            abort_if($allowed !== null && ! in_array($store->id, $allowed, true), 403, 'No autorizado para esta sucursal.');
            if ($inventory->store_id !== $store->id && ! $inventory->stores()->where('stores.id', $store->id)->exists()) {
                throw ValidationException::withMessages(['inventory_id' => 'El inventario no está asociado a la sucursal de la compra.']);
            }
            if (! empty($data['supplier_id'])) {
                Supplier::findOrFail($data['supplier_id']);
            }
            if ($purchase) {
                $purchase = Purchases::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                abort_if($allowed !== null && ! in_array($purchase->store_id, $allowed, true), 403, 'No autorizado para la sucursal original de la compra.');
                abort_if($purchase->status === 'cancelled', 422, 'No puedes editar una compra anulada.');
            }
            $previous = $purchase ? $purchase->purchaseDetails->keyBy('product_id') : collect();
            $rows = [];
            $classifications = [];
            $pendingKeys = [];
            foreach ($data['products'] as $row) {
                if (! empty($row['product_id'])) {
                    $product = Product::whereKey($row['product_id'])->lockForUpdate()->firstOrFail();
                } else {
                    $matches = Product::where(function ($q) use ($row) {
                        $q->where('sku', $row['sku']);
                        if (! empty($row['barcode'])) {
                            $q->orWhere('barcode', $row['barcode']);
                        }
                    })->lockForUpdate()->get();
                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages(['products' => 'El SKU y código de barras identifican productos distintos.']);
                    }
                    $product = $matches->first();
                }
                if ((float) $row['cost'] > 0 && round((float) $row['cost'], 8) === 0.0) {
                    throw ValidationException::withMessages(['products' => 'El costo requiere más de ocho decimales.']);
                }
                $row['cost'] = round((float) $row['cost'], 8);
                $old = $product ? $previous->get($product->id) : null;
                $usage = $row['product_usage'] ?? $old?->product_usage ?? $product?->product_usage ?? 'sale';
                $skuKey = 'sku:'.mb_strtolower($row['sku']);
                $barcodeKey = ! empty($row['barcode']) ? 'barcode:'.$row['barcode'] : null;
                $key = $product?->id ?? ($pendingKeys[$skuKey] ?? ($barcodeKey ? ($pendingKeys[$barcodeKey] ?? $skuKey) : $skuKey));
                if (! $product) {
                    if (isset($pendingKeys[$skuKey]) && $barcodeKey && isset($pendingKeys[$barcodeKey]) && $pendingKeys[$skuKey] !== $pendingKeys[$barcodeKey]) {
                        throw ValidationException::withMessages(['products' => 'Identidades contradictorias en productos nuevos.']);
                    }
                    $pendingKeys[$skuKey] = $key;
                    if ($barcodeKey) {
                        $pendingKeys[$barcodeKey] = $key;
                    }
                }
                $profile = array_key_exists('material_profile', $row) ? $row['material_profile'] : ($old ? $old->material_profile : $product?->material_profile);
                $unit = $product?->unit_of_measure ?? ($row['unit_of_measure'] ?? $profile['base_unit'] ?? 'unit');
                $profile = MaterialUnits::validate($profile, $unit ?: 'unit');
                $signature = json_encode([$usage, $profile, MaterialUnits::canonical($unit)]);
                if (isset($classifications[$key]) && $classifications[$key] !== $signature) {
                    throw ValidationException::withMessages(['products' => 'Un mismo producto tiene clasificaciones o conversiones contradictorias en la compra.']);
                }
                $classifications[$key] = $signature;
                $rows[] = compact('product', 'row', 'old', 'usage', 'profile', 'unit', 'key');
            }
            // Validate every row before reversing previous reception or changing any product.
            foreach ($rows as &$entry) {
                $entry['stock_quantity'] = MaterialUnits::stockQuantity((float) $entry['row']['quantity'], $entry['profile']);
                $entry['base_unit_cost'] = round((float) $entry['row']['cost'] / ($entry['profile']['purchase_to_base_factor'] ?? 1), 8);
                if ((float) $entry['row']['cost'] > 0 && $entry['base_unit_cost'] === 0.0) {
                    throw ValidationException::withMessages(['products' => 'El costo convertido es menor que la precisión admitida de ocho decimales.']);
                }
                if (! is_finite($entry['base_unit_cost']) || $entry['base_unit_cost'] >= 1000000000000) {
                    throw ValidationException::withMessages(['products' => 'El costo convertido excede la precisión admitida.']);
                }
            }
            unset($entry);
            if ($purchase) {
                foreach ($purchase->purchaseDetails as $detail) {
                    $this->movement($purchase, $detail, 'purchase_cancel');
                }
                $purchase->purchaseDetails()->delete();
            } else {
                $purchase = new Purchases(['user_id' => Auth::id(), 'organization_id' => $orgId]);
            }
            $purchase->fill(array_intersect_key($data, array_flip(['store_id', 'supplier_id', 'inventory_id', 'purchase_date', 'invoice_number', 'purchase_note'])));
            $purchase->total = round(array_sum(array_map(fn ($e) => (float) $e['row']['quantity'] * (float) $e['row']['cost'], $rows)), 2);
            $purchase->total_items = array_sum(array_column(array_column($rows, 'row'), 'quantity'));
            $purchase->save();
            $resolved = [];
            foreach ($rows as $entry) {
                ['product' => $product, 'row' => $row, 'old' => $old, 'usage' => $usage, 'profile' => $profile, 'unit' => $unit, 'key' => $key, 'stock_quantity' => $stockQuantity, 'base_unit_cost' => $baseUnitCost] = $entry;
                if (! $product) {
                    $product = $resolved[$key] ?? Product::create([
                        'organization_id' => $orgId, 'sku' => $row['sku'], 'name' => $row['product_name'],
                        'barcode' => $row['barcode'] ?? null, 'unit_of_measure' => $unit,
                        'product_usage' => $usage, 'material_profile' => $profile,
                        'price' => $row['price'], 'cost' => $baseUnitCost, 'min_stock' => 0, 'status' => 'active',
                    ]);
                    $resolved[$key] = $product;
                } else {
                    // Editing an old receipt without editing its classification must not roll back the current global usage.
                    if (! $old || $old->product_usage !== $usage || ! empty($row['update_product_usage'])) {
                        $product->product_usage = $usage;
                    }
                    if (array_key_exists('material_profile', $row) && (! $old || $old->material_profile !== $profile)) {
                        $product->material_profile = $profile;
                    }
                    if ($product->product_usage !== 'sale') {
                        $product->cost = $baseUnitCost;
                    }
                    $product->save();
                }
                $detail = $purchase->purchaseDetails()->create([
                    'product_id' => $product->id, 'quantity' => $row['quantity'], 'cost' => $row['cost'], 'price' => $row['price'],
                    'product_usage' => $usage, 'material_profile' => $profile,
                    'stock_quantity' => $stockQuantity, 'base_unit_cost' => $baseUnitCost,
                ]);
                $this->movement($purchase, $detail, 'purchase');
            }

            return $purchase->fresh();
        });
    }

    public function cancel(Purchases $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = Purchases::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $allowed = Auth::user()->allowedStoreIds();
            abort_if($allowed !== null && ! in_array($purchase->store_id, $allowed, true), 403, 'No autorizado para esta sucursal.');
            if ($purchase->status === 'cancelled') {
                return;
            }
            foreach ($purchase->purchaseDetails as $detail) {
                $this->movement($purchase, $detail, 'purchase_cancel');
            }
            $purchase->update(['status' => 'cancelled']);
        });
    }

    private function movement(Purchases $purchase, PurchaseDetail $detail, string $type): void
    {
        $stock = InventoryDetail::firstOrCreate(['inventory_id' => $purchase->inventory_id, 'product_id' => $detail->product_id],
            ['quantity' => 0, 'price' => $detail->price, 'status' => 'active']);
        app(InventoryMovementService::class)->recordMovement([
            'inventory_detail_id' => $stock->id, 'quantity' => $detail->stock_quantity ?? $detail->quantity, 'type' => $type,
            'user_id' => Auth::id(), 'reference_id' => $purchase->id, 'reference_type' => Purchases::class,
            'reason' => ($type === 'purchase' ? 'Ingreso' : 'Reversión').' de compra '.$purchase->id,
        ]);
    }
}
