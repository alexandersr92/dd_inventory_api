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
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'is_active',
        'wholeasaler',
        'organization_id',
        'notes',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
