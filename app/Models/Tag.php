<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class Tag extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

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
