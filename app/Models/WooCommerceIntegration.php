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

    protected $table = 'woocommerce_integrations';

    protected $fillable = [
        'organization_id',
        'store_id',
        'inventory_id',
        'woo_store_url',
        'woo_consumer_key',
        'woo_consumer_secret',
        'status',
    ];

    // SEGURIDAD: el secret no debe salir en respuestas JSON (antes cualquier rol
    // de la org lo leía en claro vía GET /woocommerce/integration).
    protected $hidden = [
        'woo_consumer_secret',
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
