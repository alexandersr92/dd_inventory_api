<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Client extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'notes',
        'wholesaler',
        'status',
        'notes'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }

    
}
