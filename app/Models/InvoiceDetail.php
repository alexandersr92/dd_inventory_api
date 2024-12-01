<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class InvoiceDetail extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'inventory_id',
        'quantity',
        'price',
        'total',
        'discount',
        'tax',
        'grand_total',
    ];


    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

/*     public function movements()
    {
        return $this->hasMany(Movement::class);
    } */


}
