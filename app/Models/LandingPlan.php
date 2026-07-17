<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPlan extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'landing_plans';

    protected $fillable = [
        'name',
        'price',
        'period',
        'discount',
        'features',
        'is_featured',
        'status'
    ];

    protected $casts = [
        'features' => 'array',
        'is_featured' => 'boolean'
    ];
}
