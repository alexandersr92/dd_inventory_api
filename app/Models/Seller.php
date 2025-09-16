<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Uuids;

class Seller extends Model
{
    use Uuids;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'status',
        'pin_hash',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'seller_store')
            ->withPivot(['status', 'assigned_at', 'revoked_at'])
            ->withTimestamps();
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

    public function users()
{
    return $this->hasMany(User::class);
}
}
