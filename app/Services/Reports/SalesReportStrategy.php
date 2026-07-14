<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class SalesReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Ventas';
    }

    protected function getReportType(): string
    {
        return 'sales';
    }

    protected function getViewName(): string
    {
        return 'reports.sales';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        $query = Invoice::where('organization_id', $organizationId)
                        ->whereNotIn('invoice_status', ['canceled', 'cancelled', 'proforma']);

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        $invoices = $query->with(['invoiceDetails.product', 'seller'])->get();

        // 1. Rentabilidad General
        $ingresosTotales = 0;
        $costoTotal = 0;

        // 2. Top Productos
        $productosVendidos = [];

        // 3. Ventas por mes
        $ventasPorMes = [];

        // 4. Ventas por vendedor
        $ventasPorVendedor = [];

        foreach ($invoices as $invoice) {
            $ingresosTotales += $invoice->grand_total;
            
            $mes = $invoice->created_at->format('Y-m');
            if (!isset($ventasPorMes[$mes])) {
                $ventasPorMes[$mes] = ['mes' => $mes, 'total_facturas' => 0, 'ingresos_totales' => 0];
            }
            $ventasPorMes[$mes]['total_facturas']++;
            $ventasPorMes[$mes]['ingresos_totales'] += $invoice->grand_total;

            $vendedor = $invoice->seller ? $invoice->seller->name : 'N/A';
            if (!isset($ventasPorVendedor[$vendedor])) {
                $ventasPorVendedor[$vendedor] = ['vendedor' => $vendedor, 'total_ventas' => 0, 'ingresos' => 0];
            }
            $ventasPorVendedor[$vendedor]['total_ventas']++;
            $ventasPorVendedor[$vendedor]['ingresos'] += $invoice->grand_total;

            foreach ($invoice->invoiceDetails as $detail) {
                $qty = $detail->quantity ?? 0;
                $product = $detail->product;
                if ($product) {
                    $costoTotal += ($product->cost * $qty);

                    if (!isset($productosVendidos[$product->id])) {
                        $productosVendidos[$product->id] = ['name' => $product->name, 'total_vendido' => 0, 'ingresos' => 0];
                    }
                    $productosVendidos[$product->id]['total_vendido'] += $qty;
                    $productosVendidos[$product->id]['ingresos'] += ($detail->price * $qty);
                }
            }
        }

        usort($productosVendidos, function($a, $b) {
            return $b['total_vendido'] <=> $a['total_vendido'];
        });

        $topProductos = array_slice($productosVendidos, 0, 5);
        $ventasPorMes = array_values($ventasPorMes);
        $ventasPorVendedor = array_values($ventasPorVendedor);

        usort($ventasPorMes, function($a, $b) { return strcmp($b['mes'], $a['mes']); });

        $margenDinero = $ingresosTotales - $costoTotal;
        $margenPorcentaje = $ingresosTotales > 0 ? ($margenDinero / $ingresosTotales) * 100 : 0;

        return [
            'rentabilidad' => [
                'ingresos' => $ingresosTotales,
                'costo' => $costoTotal,
                'margen_dinero' => $margenDinero,
                'margen_porcentaje' => $margenPorcentaje
            ],
            'ventas_por_mes' => $ventasPorMes,
            'top_productos' => $topProductos,
            'ventas_por_vendedor' => $ventasPorVendedor
        ];
    }
}
