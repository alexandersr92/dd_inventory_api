<?php

namespace App\Events;

use App\Models\Invoice;

class InvoiceCreated
{
    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * Create a new event instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Get the notification definition.
     */
    public function toNotification(): array
    {
        return [
            'event_key' => 'tenant.invoice_created',
            'organization_id' => $this->invoice->organization_id,
            'notifiables' => [],
            'data' => [
                'invoice_number' => $this->invoice->invoice_number,
                'client_name' => $this->invoice->client_name,
                'grand_total' => $this->invoice->grand_total,
                'payment_method' => $this->invoice->payment_method,
                'invoice_type' => $this->invoice->invoice_type,
                'store_name' => $this->invoice->store->name ?? '',
                'invoice_date' => $this->invoice->invoice_date,
                'total_items' => $this->invoice->total,
                'discount' => $this->invoice->discount,
                'tax' => $this->invoice->tax,
            ]
        ];
    }
}
