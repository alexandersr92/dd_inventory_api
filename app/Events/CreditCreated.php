<?php

namespace App\Events;

use App\Models\Credit;

class CreditCreated
{
    /**
     * @var Credit
     */
    public $credit;

    /**
     * Create a new event instance.
     */
    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }

    /**
     * Get the notification definition.
     */
    public function toNotification(): array
    {
        return [
            'event_key' => 'tenant.credit_created',
            'organization_id' => $this->credit->organization_id,
            'notifiables' => [],
            'data' => [
                'client_name' => $this->credit->client->name ?? '',
                'total' => $this->credit->total,
                'debt' => $this->credit->debt,
                'invoice_number' => $this->credit->invoice->invoice_number ?? '',
                'store_name' => $this->credit->store->name ?? '',
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]
        ];
    }
}
