<?php

namespace App\Support;

use App\Models\Invoice;

/** Public adapter into the existing invoice/proforma DTO. Never reads the internal revision snapshot. */
final class CustomJobProforma
{
    public static function data(Invoice $invoice): array
    {
        $c = $invoice->custom_job_commercial;

        return [
            'id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'invoice_date' => $invoice->invoice_date,
            'invoice_status' => $invoice->invoice_status, 'invoice_type' => 'proforma', 'client_id' => $c['client']['id'],
            'client_name' => $c['client']['name'], 'client_cedula_ruc' => $c['client']['cedula_ruc'],
            'total_items' => array_sum(array_column($c['articles'], 'quantity')), 'discount' => $c['totals']['discount'],
            'tax' => $c['totals']['tax'], 'grand_total' => $c['totals']['total'], 'subtotal' => $c['totals']['subtotal'],
            'currency' => $c['currency'], 'method' => 'PROFORMA', 'payment_metadata' => null, 'invoice_note' => $c['note'],
            'seller_id' => null, 'seller' => null, 'custom_job_quote_id' => $invoice->custom_job_quote_id,
            'custom_job_revision_id' => $invoice->custom_job_revision_id,
            'invoice_details' => array_map(fn ($a, $i) => [
                'id' => $invoice->custom_job_revision_id.':'.$i, 'line_type' => 'custom_job',
                'product_id' => null, 'inventory_id' => null, 'product_name' => $a['name'], 'sku' => null, 'barcode' => null,
                'quantity' => $a['quantity'], 'price' => $a['unit_price'], 'total' => $a['price'], 'discount' => $a['discount'],
                'tax' => 0, 'grand_total' => $a['net_price'], 'sort_order' => $i,
            ], $c['articles'], array_keys($c['articles'])),
        ];
    }
}
