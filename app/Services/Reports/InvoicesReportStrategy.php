<?php

namespace App\Services\Reports;

use App\Models\Invoice;

class InvoicesReportStrategy extends BaseReportStrategy
{
    protected function getReportName(): string
    {
        return 'Facturas';
    }

    protected function getReportType(): string
    {
        return 'invoices';
    }

    protected function getViewName(): string
    {
        return 'reports.invoices';
    }

    protected function fetchData(string $organizationId, array $filters): array
    {
        $query = Invoice::where('organization_id', $organizationId);

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        return $invoices->map(function ($invoice) {
            $diasMora = 0;
            if (in_array(strtolower($invoice->invoice_status), ['pending', 'credit'])) {
                $diasMora = \Carbon\Carbon::parse($invoice->created_at)->diffInDays(now(), false);
                $diasMora = $diasMora < 0 ? 0 : floor($diasMora);
            }

            return [
                'invoice_number' => $invoice->invoice_number,
                'created_at' => (string) $invoice->created_at,
                'client_name' => $invoice->client_name,
                'total' => $invoice->total, // Subtotal usually
                'tax' => $invoice->tax,
                'grand_total' => $invoice->grand_total,
                'invoice_status' => $invoice->invoice_status,
                'dias_mora' => $diasMora,
            ];
        })->toArray();
    }
}
