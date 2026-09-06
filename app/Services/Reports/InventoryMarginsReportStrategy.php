<?php

namespace App\Services\Reports;

use App\Models\InventoryDetail;
use Illuminate\Support\Facades\DB;

class InventoryMarginsReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Inventario y Márgenes';
    }

    protected function getReportType(): string
    {
        return 'inventory_margins';
    }

    protected function getViewName(): string
    {
        return 'reports.inventory_margins';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        // 1. Obtener ventas agregadas por producto (respetando filtros de tienda y fechas si existen)
        $salesQuery = DB::table('invoice_details')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.organization_id', $organizationId)
            ->whereNotIn('invoices.invoice_status', ['canceled', 'cancelled', 'proforma']);

        if (!empty($filters['store_id'])) {
            $salesQuery->where('invoices.store_id', $filters['store_id']);
        }
        if (!empty($filters['date_from'])) {
            $salesQuery->where('invoices.created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $salesQuery->where('invoices.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $salesData = $salesQuery->select(
            'invoice_details.product_id',
            DB::raw('SUM(invoice_details.quantity) as total_qty_sold'),
            DB::raw('SUM(COALESCE(NULLIF(invoice_details.grand_total, 0), NULLIF(invoice_details.total, 0), invoice_details.quantity * invoice_details.price, 0)) as total_amount_sold')
        )->groupBy('invoice_details.product_id')
        ->get()
        ->keyBy('product_id');

        // 2. Obtener los productos en inventario
        $query = InventoryDetail::with(['product', 'product.categories'])
            ->whereHas('inventory', function ($q) use ($organizationId, $filters) {
                $q->where('organization_id', $organizationId);
                if (!empty($filters['store_id'])) {
                    $q->where('store_id', $filters['store_id']);
                }
            });

        $details = $query->get();

        $productsMap = [];
        foreach ($details as $detail) {
            $product = $detail->product;
            if (!$product) {
                continue;
            }

            $productId = $product->id;
            $stock = (float) ($detail->quantity ?? 0);
            $cost = (float) ($product->cost ?? 0);
            $price = $detail->price > 0 ? (float) $detail->price : (float) ($product->price ?? 0);

            if (!isset($productsMap[$productId])) {
                $soldInfo = $salesData->get($productId);
                $soldQty = $soldInfo ? (float) $soldInfo->total_qty_sold : 0;
                $soldAmount = $soldInfo ? (float) $soldInfo->total_amount_sold : 0;
                $marginAmount = $price - $cost;
                $marginPercent = $price > 0 ? round(($marginAmount / $price) * 100, 2) : 0;

                $productsMap[$productId] = [
                    'product_id' => $productId,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'category' => $product->categories->first()->name ?? 'N/A',
                    'stock' => $stock,
                    'price' => $price,
                    'cost' => $cost,
                    'margin_amount' => $marginAmount,
                    'margin_percent' => $marginPercent,
                    'sold_qty' => $soldQty,
                    'sold_amount' => $soldAmount,
                ];
            } else {
                $productsMap[$productId]['stock'] += $stock;
            }
        }

        $items = array_values($productsMap);

        usort($items, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }
}
