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
        'wholeasaler',
        'status',
        'notes'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'client_stores', 'client_id', 'store_id');
    }
}
