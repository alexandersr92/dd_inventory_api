<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobDeliveryEvent extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];
}
