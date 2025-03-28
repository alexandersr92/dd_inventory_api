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
            'method' => $this->payment_method,
            'invoice_note' => $this->invoice_note,
            'invoice_type' => $this->invoice_type,
            'invoice_details' => $this->invoiceDetails->map(function($invoiceDetail) {
                return [
                    'id' => $invoiceDetail->id,
                    'product_id' => $invoiceDetail->product_id,
                    'sku' => $invoiceDetail->product->sku,
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
