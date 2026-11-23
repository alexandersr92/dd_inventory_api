<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class SkuRegenerationLog extends Model
{
    use HasFactory, Uuids, Multitenantable;

    protected $fillable = [
        'organization_id',
        'user_id',
        'pattern',
        'total_products',
        'sku_map',
    ];

    protected $casts = [
        'total_products' => 'integer',
        'sku_map' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
