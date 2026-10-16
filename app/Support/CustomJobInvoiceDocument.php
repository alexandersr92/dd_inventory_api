<?php

namespace App\Support;

use App\Models\Invoice;

/** Public invoice adapter for custom jobs. Internal composition/costs never leave this boundary. */
final class CustomJobInvoiceDocument
{
    public static function data(Invoice $invoice): array
    {
        $commercial = $invoice->custom_job_commercial ?? [];
        $details = $invoice->relationLoaded('invoiceDetails') ? $invoice->invoiceDetails : collect();
        $articles = $commercial['articles'] ?? [];
        $lines = $details->isNotEmpty()
            ? $details->map(fn ($line) => [
                'id' => $line->id,
                'line_type' => $line->line_type ?? 'custom_job',
                'product_id' => null,
                'inventory_id' => null,
                'product_name' => $line->custom_job_description ?? ($articles[$line->sort_order]['name'] ?? 'Trabajo personalizado'),
                'sku' => null,
                'barcode' => null,
                'quantity' => (float) $line->quantity,
                'price' => (float) $line->price,
                'total' => (float) $line->total,
                'discount' => (float) $line->discount,
                'tax' => (float) $line->tax,
                'grand_total' => (float) $line->grand_total,
                'sort_order' => (int) $line->sort_order,
            ])->values()->all()
            : array_map(fn ($article, $i) => [
                'id' => ($invoice->custom_job_revision_id ?? $invoice->id).':'.$i,
                'line_type' => 'custom_job',
                'product_id' => null,
                'inventory_id' => null,
                'product_name' => $article['name'],
                'sku' => null,
                'barcode' => null,
                'quantity' => $article['quantity'],
                'price' => $article['unit_price'],
                'total' => $article['price'],
                'discount' => $article['discount'] ?? 0,
                'tax' => 0,
                'grand_total' => $article['net_price'],
                'sort_order' => $i,
            ], $articles, array_keys($articles));

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'invoice_status' => $invoice->invoice_status,
            'invoice_type' => $invoice->invoice_type,
            'client_id' => $commercial['client']['id'] ?? $invoice->client_id,
            'client_name' => $commercial['client']['name'] ?? $invoice->client_name,
            'client_cedula_ruc' => $commercial['client']['cedula_ruc'] ?? $invoice->client?->cedula_ruc,
            'total_items' => array_sum(array_column($articles, 'quantity')),
            'discount' => $commercial['totals']['discount'] ?? $invoice->discount,
            'tax' => $commercial['totals']['tax'] ?? $invoice->tax,
            'grand_total' => $commercial['totals']['total'] ?? $invoice->grand_total,
            'subtotal' => $commercial['totals']['subtotal'] ?? null,
            'currency' => $commercial['currency'] ?? 'NIO',
            'method' => $invoice->payment_method,
            'payment_metadata' => $invoice->payment_metadata,
            'invoice_note' => $invoice->invoice_note,
            'seller_id' => $invoice->seller_id,
            'seller' => $invoice->seller?->name,
            'custom_job_quote_id' => $invoice->custom_job_quote_id,
            'custom_job_revision_id' => $invoice->custom_job_revision_id,
            ...($invoice->custom_job_order_id ? ['custom_job_order_id' => $invoice->custom_job_order_id] : []),
            'invoice_details' => $lines,
        ];
    }
}
