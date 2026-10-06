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
        'cost',
        'product_usage',
        'material_profile',
        'stock_quantity',
        'base_unit_cost',
      
    ];

    protected $casts = ['material_profile' => 'array', 'quantity' => 'float', 'cost' => 'float', 'price' => 'float', 'stock_quantity' => 'float', 'base_unit_cost' => 'float'];

    public function purchase()
    {
        return $this->belongsTo(Purchases::class, 'purchase_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }


}
