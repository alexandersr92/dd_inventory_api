<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Purchases extends Model
{
    use HasFactory;
    use Uuids;
    
    protected $fillable = [
        'user_id',
        'organization_id',
        'store_id',
        'supplier_id',
        'inventory_id',
        'total',
        'purchase_date',
        'purchase_note',
        'total_items',
        'status',
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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id');
    }

  

    
}
