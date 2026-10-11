<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobMaterialAllocation extends Model
{
    use Multitenantable, Uuids;

    protected $casts = ['material_index' => 'integer'];

    protected $guarded = ['id', 'organization_id'];
}
