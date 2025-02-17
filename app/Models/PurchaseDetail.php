<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;


class PurchaseDetail extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'price',
      
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchases::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }


}
