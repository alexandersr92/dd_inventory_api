<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Organization extends Model
{
    use Uuids, HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'website',
        'logo',
        'description',
        'status',
        'owner_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'organization_modules')
            ->using(OrganizationModule::class)
            ->withPivot('status');
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

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }

    public function sellers()
    {
        return $this->hasMany(Seller::class);
    }
}
