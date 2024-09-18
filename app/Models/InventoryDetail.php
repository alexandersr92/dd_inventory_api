<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;


class InventoryDetail extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'inventory_id',
        'product_id',
        'quantity',
        'status',
        'description',
        'address',
        'price',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
