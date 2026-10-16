<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobCancellation extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = [];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'effects' => 'array',
        'occurred_at' => 'datetime',
    ];
}
