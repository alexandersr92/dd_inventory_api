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
        'max_sellers',
        'max_stores',
        'max_monthly_invoices',
        'tenancy_type',
        'price_monthly',
        'price_annual',
        'currency',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'max_sellers' => 'integer',
            'max_stores' => 'integer',
            'max_monthly_invoices' => 'integer',
            'price_monthly' => 'float',
            'price_annual' => 'float',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Meses de licencia según el ciclo de cobro elegido.
     */
    public static function monthsForCycle(string $cycle): int
    {
        return $cycle === 'annual' ? 12 : 1;
    }

    /**
     * Precio del plan para el ciclo indicado.
     */
    public function priceForCycle(string $cycle): float
    {
        return (float) ($cycle === 'annual' ? $this->price_annual : $this->price_monthly);
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }
}
