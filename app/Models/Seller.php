<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Seller extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
