<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobPayment extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'amount_nio' => 'decimal:2', 'exchange_rate' => 'decimal:8', 'payment_metadata' => 'array', 'occurred_at' => 'datetime'];

    public function appliedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'applied_invoice_id');
    }
}
