<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use Uuids;

    protected $connection = 'central';
    protected $table = 'subscription_invoices';

    protected $fillable = [
        'number',
        'organization_id',
        'payment_submission_id',
        'plan_id',
        'concept',
        'period_start',
        'period_end',
        'amount',
        'currency',
        'payment_method',
        'reference',
        'issuer',
        'customer',
        'issued_at',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'issuer' => 'array',
            'customer' => 'array',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
