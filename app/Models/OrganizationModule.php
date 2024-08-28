<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class OrganizationModule extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'module_id',
        'status',
        'start_date',
        'end_date',
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
