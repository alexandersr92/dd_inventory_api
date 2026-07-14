<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class InventoryMovement extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $fillable = [
        'organization_id',
        'inventory_id',
        'inventory_detail_id',
        'product_id',
        'store_id',
        'user_id',
        'seller_id',
        'type',
        'direction',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'reference_type',
        'reference_id',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function inventoryDetail()
    {
        return $this->belongsTo(InventoryDetail::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
