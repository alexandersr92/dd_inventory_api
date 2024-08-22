<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'module_id',
        'is_active',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function module()
    {
        return $this->belongsToMany(Module::class);
    }
}
