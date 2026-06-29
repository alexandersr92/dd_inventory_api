<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'notification_events';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'scope',
        'name',
        'description',
        'default_channels',
        'conditions_schema',
    ];

    protected $casts = [
        'default_channels' => 'array',
        'conditions_schema' => 'array',
    ];
}
