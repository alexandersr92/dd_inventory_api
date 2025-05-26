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

            $totalItems = $invoice->invoiceDetails->count('quantity');
            return [
                'id' => $invoice->id,
                'client_name' => $invoice->client_name,
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
