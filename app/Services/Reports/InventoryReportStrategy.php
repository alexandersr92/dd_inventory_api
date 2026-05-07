<?php

namespace App\Services\Reports;

use App\Models\Inventory;

class InventoryReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Inventarios';
    }

    protected function getReportType(): string
    {
        return 'inventory';
    }

    protected function getViewName(): string
    {
        return 'reports.inventory';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        $query = \App\Models\InventoryDetail::with(['product', 'product.categories'])
            ->whereHas('inventory', function ($q) use ($organizationId, $filters) {
                $q->where('organization_id', $organizationId);
                if (!empty($filters['store_id'])) {
                    $q->where('store_id', $filters['store_id']);
                }
            });

        $details = $query->get();

        return $details->map(function ($detail) {
            $product = $detail->product;
            if (!$product) return null;

            $stock = $detail->quantity ?? 0;
            $minStock = $product->min_stock ?? 0;
            $cost = $product->cost ?? 0;
            $price = $product->price ?? 0;

            return [
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->categories->first()->name ?? 'N/A',
                'stock' => $stock,
                'min_stock' => $minStock,
                'cost' => $cost,
                'price' => $price,
                'total_value' => $stock * $cost,
                'last_movement' => $detail->updated_at ? $detail->updated_at->format('Y-m-d') : 'N/A',
                'is_low_stock' => $stock <= $minStock,
            ];
        })->filter()->values()->toArray();
    }
}
