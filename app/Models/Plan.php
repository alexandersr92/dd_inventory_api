<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Plan extends Model
{
    use HasFactory;
    use Uuids;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'duration_months',
        'max_sellers',
        'max_stores',
        'max_monthly_invoices',
        'tenancy_type',
        'price',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'max_sellers' => 'integer',
            'max_stores' => 'integer',
            'max_monthly_invoices' => 'integer',
            'price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }
}
