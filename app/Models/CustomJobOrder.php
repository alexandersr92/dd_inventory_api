<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobOrder extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['version' => 'integer', 'snapshot' => 'array', 'commercial' => 'array', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(CustomJobOrderItem::class, 'order_id')->orderBy('position');
    }
}
