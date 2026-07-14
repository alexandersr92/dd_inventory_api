<?php

namespace App\Services\Reports;

use App\Models\Credit;
use Carbon\Carbon;

class CreditsReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Créditos y Cartera';
    }

    protected function getReportType(): string
    {
        return 'credits';
    }

    protected function getViewName(): string
    {
        return 'reports.credits';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        $query = Credit::where('organization_id', $organizationId)->with('client');

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        $credits = $query->orderBy('created_at', 'desc')->get();

        $totalOtorgado = 0;
        $totalDeuda = 0;

        $tramos = [
            '0_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'mas_90' => 0,
        ];

        $detalleCreditos = [];

        foreach ($credits as $credit) {
            $totalOtorgado += $credit->total;
            $totalDeuda += $credit->debt;

            $dias = Carbon::parse($credit->created_at)->diffInDays(now());

            if ($credit->debt > 0) {
                if ($dias <= 30) {
                    $tramos['0_30'] += $credit->debt;
                } elseif ($dias <= 60) {
                    $tramos['31_60'] += $credit->debt;
                } elseif ($dias <= 90) {
                    $tramos['61_90'] += $credit->debt;
                } else {
                    $tramos['mas_90'] += $credit->debt;
                }
            }

            $detalleCreditos[] = [
                'cliente' => $credit->client ? $credit->client->name : 'N/A',
                'fecha' => $credit->created_at->format('Y-m-d'),
                'total' => $credit->total,
                'pagado' => $credit->total - $credit->debt,
                'deuda' => $credit->debt,
                'estado' => $credit->credit_status,
                'dias_antiguedad' => floor($dias)
            ];
        }

        $totalRecuperado = $totalOtorgado - $totalDeuda;

        return [
            'resumen' => [
                'total_otorgado' => $totalOtorgado,
                'total_recuperado' => $totalRecuperado,
                'total_deuda' => $totalDeuda
            ],
            'tramos' => $tramos,
            'detalle' => $detalleCreditos
        ];
    }
}
