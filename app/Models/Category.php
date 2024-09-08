<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Category extends Model
{
    use HasFactory;
    use Uuids;

    protected $guarded = [];

    protected $fillable = [
        'name',
        'organization_id',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
