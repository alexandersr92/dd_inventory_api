<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class PaymentProvider extends Model
{
    use Uuids;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'driver',
        'is_active',
        'is_default',
        'mode',
        'instructions',
        'supports_receipt',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supports_receipt' => 'boolean',
            // Las credenciales de pasarelas se guardan cifradas en reposo.
            'config' => 'encrypted:array',
        ];
    }
}
