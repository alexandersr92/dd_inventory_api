<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Module extends Model
{
    use Uuids;
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'path',
        'status',
    ];

    public function organization()
    {
        return $this->belongsToMany(Organization::class, 'organization_modules')
            ->using(OrganizationModule::class);
    }
}
