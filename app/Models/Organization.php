<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Organization extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'website',
        'logo',
        'description',
        'status',

    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }


    public function modules()
    {
        return $this->belongsToMany(Module::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }
}
