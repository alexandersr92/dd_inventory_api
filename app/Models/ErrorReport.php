<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class ErrorReport extends Model
{
    use Uuids;

    protected $connection = 'central';
    protected $table = 'error_reports';

    protected $fillable = [
        'admin_id',
        'admin_name',
        'organization_id',
        'organization_name',
        'reporter_name',
        'reporter_email',
        'source',
        'message',
        'page_url',
        'user_agent',
        'screenshot_path',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
