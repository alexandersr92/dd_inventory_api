<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

abstract class CustomJobResource extends Model
{
    use Multitenantable, Uuids;

    protected $fillable = ['name', 'status', 'configuration', 'process_id'];

    protected $casts = ['configuration' => 'array'];
}
