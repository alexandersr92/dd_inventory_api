<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingMedia extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'landing_media';

    protected $fillable = [
        'filename',
        'disk_path',
        'url',
        'size_bytes',
        'mime_type'
    ];
}
