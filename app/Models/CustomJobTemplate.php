<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobTemplate extends Model
{
    use Multitenantable, Uuids;

    protected $fillable = ['name', 'description', 'status', 'composition'];

    protected $casts = ['composition' => 'array', 'schema_version' => 'integer'];
}
