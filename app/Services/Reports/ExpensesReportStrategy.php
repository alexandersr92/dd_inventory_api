<?php

namespace App\Services\Reports;

use App\Models\Purchases;

class ExpensesReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Gastos y Compras';
    }

    protected function getReportType(): string
    {
        return 'expenses';
    }

    protected function getViewName(): string
    {
        return 'reports.expenses';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        $query = Purchases::where('organization_id', $organizationId);

        if (!empty($filters['date_from'])) {
            $query->where('purchase_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('purchase_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        $purchases = $query->with('supplier')->orderBy('purchase_date', 'desc')->get()->map(function($purchase) {
            return [
                'date' => $purchase->purchase_date,
                'reference' => $purchase->purchase_note,
                'supplier' => $purchase->supplier ? $purchase->supplier->name : 'N/A',
                'status' => $purchase->status,
                'total' => $purchase->total
            ];
        });

        return $purchases->toArray();
    }
}
