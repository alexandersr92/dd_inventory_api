<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class ProductMeta extends Model
{
    use HasFactory;
    use Uuids;

    protected $guarded = [];

    protected $fillable = [
        'product_id',
        'key',
        'value',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
