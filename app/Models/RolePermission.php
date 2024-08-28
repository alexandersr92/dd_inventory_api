<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class RolePermission extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'role_id',
        'module_id',
        'store_id',
        'read',
        'create',
        'update',
        'delete',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function module()
    {
        return $this->hasOne(Module::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }
}
