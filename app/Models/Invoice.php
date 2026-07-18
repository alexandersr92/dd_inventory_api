<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class Invoice extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $fillable = [
        'user_id',
        'organization_id',
        'client_id',
        'store_id',
        'seller_id',
        'invoice_number',
        'invoice_date',
        'invoice_note',
        'client_name',
        'total',
        'discount',
        'tax',
        'grand_total',
        'paid_in_usd',
        'paid_in_nio',
        'exchange_rate',
        'payment_method',
        'payment_date',
        'invoice_status',
        'invoice_type', 
        'payment_metadata',
        'cash_session_id',
        'source',
        'offline_reference',
        'is_offline',
        'offline_number',
    ];

    protected $casts = [
        'payment_metadata' => 'array',
        'paid_in_usd' => 'float',
        'paid_in_nio' => 'float',
        'exchange_rate' => 'float',
    ];

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class)->orderBy('sort_order', 'asc');
    }


    public function credit()
    {
        return $this->hasOne(Credit::class);
    }


    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

}
