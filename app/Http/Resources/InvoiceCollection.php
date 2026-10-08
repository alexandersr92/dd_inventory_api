<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InvoiceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($invoice) {
            if ($invoice->custom_job_quote_id) {
                $public = \App\Support\CustomJobProforma::data($invoice);
                return [...array_intersect_key($public, array_flip(['id', 'client_name', 'client_cedula_ruc', 'invoice_number', 'invoice_date', 'invoice_status', 'total_items', 'grand_total', 'method', 'seller', 'currency', 'custom_job_quote_id', 'custom_job_revision_id'])), 'created_at' => $invoice->created_at, 'client' => null];
            }

    
            $totalItems = $invoice->invoiceDetails->sum(function ($detail) {
                return $detail->quantity;
            });
            return [
                'id' => $invoice->id,
                'client_name' => $invoice->client_name,
                'client_cedula_ruc' => $invoice->client->cedula_ruc ?? null,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'created_at' => $invoice->created_at,
                'invoice_status' => $invoice->invoice_status,
                'total_items' => $totalItems,
                'client' => $invoice->client,
                'grand_total' => $invoice->grand_total,
                'method' => $invoice->payment_method,
                'seller' => $invoice->seller->name ?? null,
            ];
        })->toArray();
    }
}
