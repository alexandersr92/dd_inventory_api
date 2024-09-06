<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'name',
        'organization_id',
        'guard_name'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
