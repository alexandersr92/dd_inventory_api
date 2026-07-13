<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditDetail;
use App\Models\Purchases;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function metrics(Request $request)
    {
        $storeId = $request->query('store_id');
        $refresh = $request->query('refresh') === 'true';
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        
        $orgId = Auth::user()->organization_id;
        
        $cacheKey = "dashboard_metrics_{$orgId}_" . ($storeId ?? 'all') . "_" . ($dateFrom ?? 'none') . "_" . ($dateTo ?? 'none');
        
        if ($refresh) {
            Cache::forget($cacheKey);
        }
        
        $data = Cache::remember($cacheKey, 300, function () use ($storeId, $orgId, $dateFrom, $dateTo) {
            // Calcular rangos de fechas (periodo actual vs periodo anterior de igual duración)
            if ($dateFrom && $dateTo) {
                $start = Carbon::parse($dateFrom);
                $end = Carbon::parse($dateTo);
                $days = $start->diffInDays($end) + 1;
                
                $currentStart = $dateFrom;
                $currentEnd = $dateTo;
                $prevStart = $start->copy()->subDays($days)->toDateString();
                $prevEnd = $start->copy()->subDay()->toDateString();
            } else {
                $currentStart = Carbon::today()->toDateString();
                $currentEnd = Carbon::today()->toDateString();
                $prevStart = Carbon::yesterday()->toDateString();
                $prevEnd = Carbon::yesterday()->toDateString();
            }
            
            // --- 1. KPI Ventas ---
            $salesTodayQuery = Invoice::where('organization_id', $orgId);
            $salesYesterdayQuery = Invoice::where('organization_id', $orgId);
            
            if ($storeId) {
                $salesTodayQuery->where('store_id', $storeId);
                $salesYesterdayQuery->where('store_id', $storeId);
            }
            
            $salesToday = $salesTodayQuery->whereBetween('invoice_date', [$currentStart, $currentEnd])->count();
            $salesYesterday = $salesYesterdayQuery->whereBetween('invoice_date', [$prevStart, $prevEnd])->count();
            
            // --- 2. KPI Ingresos ---
            $revenueTodayQuery = Invoice::where('organization_id', $orgId)->where('invoice_status', '!=', 'canceled');
            $revenueYesterdayQuery = Invoice::where('organization_id', $orgId)->where('invoice_status', '!=', 'canceled');
            
            if ($storeId) {
                $revenueTodayQuery->where('store_id', $storeId);
                $revenueYesterdayQuery->where('store_id', $storeId);
            }
            
            $revenueToday = $revenueTodayQuery->whereBetween('invoice_date', [$currentStart, $currentEnd])->sum('grand_total');
            $revenueYesterday = $revenueYesterdayQuery->whereBetween('invoice_date', [$prevStart, $prevEnd])->sum('grand_total');
            
            // --- 3. KPI Clientes Nuevos ---
            $clientsToday = Client::where('organization_id', $orgId)->whereBetween(DB::raw('DATE(created_at)'), [$currentStart, $currentEnd])->count();
            $clientsYesterday = Client::where('organization_id', $orgId)->whereBetween(DB::raw('DATE(created_at)'), [$prevStart, $prevEnd])->count();
            
            // --- 4. KPI Créditos Pendientes ---
            $creditsQuery = Credit::where('organization_id', $orgId);
            if ($storeId) {
                $creditsQuery->where('store_id', $storeId);
            }
            $creditsPending = $creditsQuery->sum('debt');
            
            // --- 4.5 KPI Usuarios Activos ---
            $activeSellers = \App\Models\Seller::where('organization_id', $orgId)->where('status', 'active')->count();
            $totalSellers = \App\Models\Seller::where('organization_id', $orgId)->count();
            
            // --- 5. Ventas Recientes ---
            $recentSalesQuery = Invoice::where('organization_id', $orgId)->with('client');
            if ($storeId) {
                $recentSalesQuery->where('store_id', $storeId);
            }
            $recentSales = $recentSalesQuery->orderBy('created_at', 'desc')->limit(5)->get()->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'client_name' => $invoice->client_name,
                    'invoice_date' => $invoice->invoice_date,
                    'grand_total' => $invoice->grand_total,
                ];
            });
            
            // --- 6. Últimos Abonos ---
            $recentAbonosQuery = CreditDetail::whereHas('credit', function($q) use ($orgId, $storeId) {
                $q->where('organization_id', $orgId);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })->with('credit.client');
            
            $recentAbonos = $recentAbonosQuery->orderBy('created_at', 'desc')->limit(5)->get()->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'clientName' => $detail->credit->client->name ?? 'Cliente General',
                    'invoiceNum' => $detail->credit->invoice->invoice_number ?? 'CRÉDITO',
                    'amount' => (float)$detail->amount,
                    'time' => $detail->created_at->diffForHumans()
                ];
            });
            
            // --- 7. Métodos de Pago ---
            $paymentsQuery = Invoice::where('organization_id', $orgId)->where('invoice_status', '!=', 'canceled');
            if ($storeId) {
                $paymentsQuery->where('store_id', $storeId);
            }
            $payments = $paymentsQuery->whereBetween('invoice_date', [$currentStart, $currentEnd])
                ->groupBy('payment_method')
                ->select('payment_method', DB::raw('SUM(grand_total) as value'))
                ->get()
                ->map(function($p) {
                    return [
                        'name' => ucfirst(strtolower($p->payment_method)) ?: 'Otro',
                        'value' => (float)$p->value
                    ];
                });
            
            // --- 8. Inventario Bajo ---
            $lowStockQuery = Product::where('organization_id', $orgId)
                ->withSum(['inventoryDetails as total_stock' => function($q) use ($storeId) {
                    if ($storeId) {
                        $q->whereHas('inventory', function($invQ) use ($storeId) {
                            $invQ->where('store_id', $storeId);
                        });
                    }
                }], 'quantity');
                
            $lowStock = $lowStockQuery->get()->filter(function($p) {
                $stock = $p->total_stock ?? 0;
                return $stock < $p->min_stock;
            })->take(5)->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock' => (int)($p->total_stock ?? 0),
                    'min_stock' => $p->min_stock
                ];
            })->values();

            // --- 9. Productos Más Vendidos ---
            $topProducts = \App\Models\InvoiceDetail::whereHas('invoice', function($q) use ($orgId, $storeId, $currentStart, $currentEnd) {
                $q->where('organization_id', $orgId);
                $q->whereBetween('invoice_date', [$currentStart, $currentEnd]);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->with('product:id,name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(function($detail) {
                return [
                    'name' => $detail->product->name ?? 'Producto',
                    'cantidad' => (int)$detail->quantity
                ];
            });

            // --- 10. Históricos para Gráfico Configurable ---
            // Ventas e Ingresos últimos 12 meses
            $salesHistoryQuery = Invoice::where('organization_id', $orgId)
                ->where('invoice_status', '!=', 'cancelled')
                ->where('created_at', '>=', Carbon::now()->subMonths(12));
            if ($storeId) {
                $salesHistoryQuery->where('store_id', $storeId);
            }
            $salesHistory = $salesHistoryQuery->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"), DB::raw('COUNT(*) as sales'), DB::raw('SUM(grand_total) as revenue'))
                ->get()->keyBy('date');

            // Compras últimos 12 meses
            $purchasesHistoryQuery = Purchases::where('organization_id', $orgId)
                ->where('created_at', '>=', Carbon::now()->subMonths(12));
            if ($storeId) {
                $purchasesHistoryQuery->where('store_id', $storeId);
            }
            $purchasesHistory = $purchasesHistoryQuery->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"), DB::raw('SUM(total) as purchases'))
                ->get()->keyBy('date');

            // Créditos últimos 12 meses
            $creditsHistoryQuery = Credit::where('organization_id', $orgId)
                ->where('created_at', '>=', Carbon::now()->subMonths(12));
            if ($storeId) {
                $creditsHistoryQuery->where('store_id', $storeId);
            }
            $creditsHistory = $creditsHistoryQuery->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"), DB::raw('SUM(debt) as credits'))
                ->get()->keyBy('date');

            // Clientes nuevos últimos 12 meses
            $clientsHistory = Client::where('organization_id', $orgId)
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"), DB::raw('COUNT(*) as clients'))
                ->get()->keyBy('date');

            // Generar estructura consolidada mensual (últimos 6 meses)
            $monthlyHistory = [];
            for ($i = 5; $i >= 0; $i--) {
                $dateKey = Carbon::now()->subMonths($i)->format('Y-m');
                $dateLabel = Carbon::now()->subMonths($i)->locale('es')->shortMonthName;
                
                $monthlyHistory[] = [
                    'name' => ucfirst($dateLabel),
                    'sales' => isset($salesHistory[$dateKey]) ? (int)$salesHistory[$dateKey]->sales : 0,
                    'revenue' => isset($salesHistory[$dateKey]) ? (float)$salesHistory[$dateKey]->revenue : 0.0,
                    'purchases' => isset($purchasesHistory[$dateKey]) ? (float)$purchasesHistory[$dateKey]->purchases : 0.0,
                    'credits' => isset($creditsHistory[$dateKey]) ? (float)$creditsHistory[$dateKey]->credits : 0.0,
                    'clients' => isset($clientsHistory[$dateKey]) ? (int)$clientsHistory[$dateKey]->clients : 0,
                ];
            }
            
            return [
                'kpis' => [
                    'sales' => [
                        'current' => $salesToday,
                        'previous' => $salesYesterday,
                    ],
                    'revenue' => [
                        'current' => (float)$revenueToday,
                        'previous' => (float)$revenueYesterday,
                    ],
                    'clients' => [
                        'current' => $clientsToday,
                        'previous' => $clientsYesterday,
                    ],
                    'credits' => [
                        'current' => (float)$creditsPending,
                        'previous' => (float)$creditsPending * 0.95 // Comparativa
                    ],
                    'activeUsers' => [
                        'current' => $activeSellers,
                        'previous' => $totalSellers,
                    ]
                ],
                'recentSales' => $recentSales,
                'recentAbonos' => $recentAbonos,
                'paymentMethods' => $payments,
                'lowStock' => $lowStock,
                'topProducts' => $topProducts,
                'monthlyHistory' => $monthlyHistory
            ];
        });
        
        return response()->json($data);
    }

    public function chart(Request $request)
    {
        $metric = $request->query('metric', 'sales');
        $range = $request->query('range', '6m');
        $freq = $request->query('freq', 'monthly');
        $storeId = $request->query('store_id');
        
        $orgId = Auth::user()->organization_id;
        
        $cacheKey = "dashboard_chart_{$orgId}_{$metric}_{$range}_{$freq}_" . ($storeId ?? 'all');
        
        $data = Cache::remember($cacheKey, 300, function () use ($orgId, $storeId, $metric, $range, $freq) {
            $now = Carbon::now();
            $days = 180;
            if ($range === '7d') $days = 7;
            elseif ($range === '1m') $days = 30;
            elseif ($range === '3m') $days = 90;
            elseif ($range === '6m') $days = 180;
            elseif ($range === '1y') $days = 365;
            
            $startDate = $now->copy()->subDays($days)->toDateString();
            $endDate = $now->toDateString();
            
            $prevStartDate = $now->copy()->subDays($days * 2)->toDateString();
            $prevEndDate = $now->copy()->subDays($days)->subDay()->toDateString();
            
            $currentData = $this->getQueryData($orgId, $storeId, $metric, $startDate, $endDate, $freq);
            $previousData = $this->getQueryData($orgId, $storeId, $metric, $prevStartDate, $prevEndDate, $freq);
            
            return $this->consolidateChartPoints($startDate, $endDate, $currentData, $previousData, $freq, $days);
        });
        
        return response()->json($data);
    }

    private function getQueryData($orgId, $storeId, $metric, $start, $end, $freq)
    {
        switch ($metric) {
            case 'revenue':
                $query = Invoice::where('organization_id', $orgId)->where('invoice_status', '!=', 'canceled');
                $selectVal = 'SUM(grand_total)';
                $dateCol = 'invoice_date';
                break;
            case 'sales':
                $query = Invoice::where('organization_id', $orgId);
                $selectVal = 'COUNT(*)';
                $dateCol = 'invoice_date';
                break;
            case 'purchases':
                $query = Purchases::where('organization_id', $orgId);
                $selectVal = 'SUM(total)';
                $dateCol = 'created_at';
                break;
            case 'credits':
                $query = Credit::where('organization_id', $orgId);
                $selectVal = 'SUM(total)';
                $dateCol = 'created_at';
                break;
            case 'clients':
                $query = Client::where('organization_id', $orgId);
                $selectVal = 'COUNT(*)';
                $dateCol = 'created_at';
                break;
            default:
                return [];
        }
        
        if ($storeId && in_array($metric, ['revenue', 'sales', 'credits'])) {
            $query->where('store_id', $storeId);
        }
        
        if ($freq === 'daily') {
            $dateFormat = "DATE({$dateCol})";
        } elseif ($freq === 'weekly') {
            $dateFormat = "YEARWEEK({$dateCol}, 1)";
        } else {
            $dateFormat = "DATE_FORMAT({$dateCol}, '%Y-%m')";
        }
        
        return $query->whereBetween($dateCol, [$start, $end])
            ->groupBy(DB::raw($dateFormat))
            ->select(DB::raw("{$dateFormat} as date_key"), DB::raw("{$selectVal} as total_val"))
            ->get()
            ->pluck('total_val', 'date_key')
            ->toArray();
    }

    private function consolidateChartPoints($start, $end, $currentData, $previousData, $freq, $days)
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $points = [];
        
        if ($freq === 'daily') {
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $dateStr = $current->toDateString();
                $prevDateStr = $current->copy()->subDays($days)->toDateString();
                
                if ($days <= 7) {
                    $label = $current->locale('es')->shortDayName;
                } else {
                    $label = $current->format('d/m');
                }
                
                $points[] = [
                    'name' => ucfirst($label),
                    'actual' => (float)($currentData[$dateStr] ?? 0.0),
                    'anterior' => (float)($previousData[$prevDateStr] ?? 0.0),
                ];
                
                $current->addDay();
            }
        } elseif ($freq === 'weekly') {
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $weekKey = $current->format('oW');
                $prevWeekKey = $current->copy()->subDays($days)->format('oW');
                
                $points[] = [
                    'name' => 'Sem ' . $current->format('W'),
                    'actual' => (float)($currentData[$weekKey] ?? 0.0),
                    'anterior' => (float)($previousData[$prevWeekKey] ?? 0.0),
                ];
                
                $current->addWeek();
            }
        } else {
            $current = $startDate->copy()->startOfMonth();
            $endMonth = $endDate->copy()->startOfMonth();
            while ($current->lte($endMonth)) {
                $monthKey = $current->format('Y-m');
                $prevMonthKey = $current->copy()->subDays($days)->format('Y-m');
                
                $points[] = [
                    'name' => ucfirst($current->locale('es')->shortMonthName),
                    'actual' => (float)($currentData[$monthKey] ?? 0.0),
                    'anterior' => (float)($previousData[$prevMonthKey] ?? 0.0),
                ];
                
                $current->addMonth();
            }
        }
        
        return $points;
    }
}
