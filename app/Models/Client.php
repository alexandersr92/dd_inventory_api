<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
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
