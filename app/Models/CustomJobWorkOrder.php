<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobWorkOrder extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['cancelled_at' => 'datetime'];
}
