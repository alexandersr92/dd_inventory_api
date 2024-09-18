<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;


class Inventory extends Model
{
    use HasFactory;
    use Uuids;

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

    public function inventoryDetails()
    {
        return $this->hasMany(InventoryDetail::class);
    }
}
