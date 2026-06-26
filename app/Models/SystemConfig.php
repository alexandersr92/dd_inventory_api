<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Uuids;

class SystemConfig extends Model
{
    use HasFactory, Uuids;

    protected $connection = 'central';
    protected $table = 'system_configs';

    protected $fillable = [
        'key',
        'value',
    ];
}
