<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobQuoteRevision extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['number' => 'integer', 'snapshot' => 'array', 'commercial' => 'array'];
}
