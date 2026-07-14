<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\Uuids;

class UserStore extends Pivot
{
    use Uuids;

    protected $connection = 'central';
    protected $table = 'user_store';
    protected $primaryKey = 'id';
    public $incrementing = false;
}
