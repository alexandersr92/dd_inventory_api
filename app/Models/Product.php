<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Product extends Model
{
    use Uuids;
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'name',
        'barcode',
        'sku',
        'unit_of_masure',
        'image',
        'description',
        'price',
        'min_stock',
        'status',
        'organization_id',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }


    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function meta()
    {
        return $this->hasMany(ProductMeta::class);
    }
}
