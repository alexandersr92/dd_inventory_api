<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobOrderItem extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['active' => 'boolean', 'position' => 'integer', 'quantity' => 'float', 'commercial' => 'array', 'composition' => 'array'];
}
