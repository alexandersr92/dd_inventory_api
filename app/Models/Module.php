<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Module extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'route',
        'is_active',
    ];

    public function roles()
    {
        return $this->belongsToMany(RoleMeta::class);
    }

    public function organization()
    {
        return $this->belongsToMany(Organization::class);
    }
}
