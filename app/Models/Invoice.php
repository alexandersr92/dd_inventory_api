<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Invoice extends Model
{
    use HasFactory;
    use Uuids;

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
        'payment_method',
        'payment_date',
        'invoice_status',
        'invoice_type', 
  


    ];

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
        return $this->hasMany(InvoiceDetail::class);
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
