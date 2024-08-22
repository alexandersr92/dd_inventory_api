<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'module_id',
        'read',
        'create',
        'update',
        'delete',
        'is_active',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function module()
    {
        return $this->hasOne(Module::class);
    }
}
