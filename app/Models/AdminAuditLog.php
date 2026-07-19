<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class AdminAuditLog extends Model
{
    use Uuids;

    protected $connection = 'central';

    protected $fillable = [
        'admin_id',
        'admin_name',
        'action',
        'target_type',
        'target_id',
        'description',
        'ip',
    ];
}
