<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class Inventory extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $fillable = [
        'organization_id',
        'store_id',
        'name',
        'description',
        'status',
        'address',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'inventory_store')->withTimestamps();
    }

    public function inventoryDetails()
    {
        return $this->hasMany(InventoryDetail::class);
    }
}
