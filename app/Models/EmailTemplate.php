<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Uuids;

class EmailTemplate extends Model
{
    use HasFactory, Uuids;

    protected $connection = 'central';
    protected $table = 'email_templates';

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
    ];
}
