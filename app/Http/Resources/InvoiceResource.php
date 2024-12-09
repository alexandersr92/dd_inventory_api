<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'invoice_status' => $this->invoice_status,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'total_items' => $this->total,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'grand_total' => $this->grand_total,
            'method' => $this->method,
            'invoice_details' => $this->invoiceDetails->map(function($invoiceDetail) {
                return [
                    'id' => $invoiceDetail->id,
                    'product_id' => $invoiceDetail->product_id,
                    'product_name' => $invoiceDetail->product->name,
                    'inventory_id' => $invoiceDetail->inventory_id,
                    'quantity' => $invoiceDetail->quantity,
                    'price' => $invoiceDetail->price,
                    'total' => $invoiceDetail->total,
                ];
            })->toArray(),
        ];
    }
}
