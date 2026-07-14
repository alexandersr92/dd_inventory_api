<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingContent extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'landing_contents';

    protected $fillable = [
        'section_key',
        'content'
    ];

    protected $casts = [
        'content' => 'array'
    ];
}
