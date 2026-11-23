<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class ProductSkuSetting extends Model
{
    use HasFactory, Uuids, Multitenantable;

    protected $fillable = [
        'organization_id',
        'enabled',
        'pattern',
        'counter',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'counter' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
