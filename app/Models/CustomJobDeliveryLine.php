<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobDeliveryLine extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['quantity' => 'integer'];

    public function event()
    {
        return $this->belongsTo(CustomJobDeliveryEvent::class, 'event_id');
    }
}
