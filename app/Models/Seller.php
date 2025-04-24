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
        'organization_id',
        'name',
        'code',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class);
    }
    public function credit()
    {
        return $this->hasMany(Credit::class);
    }

    public function creditDetails()
    {
        return $this->hasMany(CreditDetail::class);
    }
}
