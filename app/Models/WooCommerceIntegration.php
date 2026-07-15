<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class WooCommerceIntegration extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $fillable = [
        'organization_id',
        'store_id',
        'inventory_id',
        'woo_store_url',
        'woo_consumer_key',
        'woo_consumer_secret',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
