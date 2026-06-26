<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;
class Setting extends Model
{
    use Uuids;
    use HasFactory;
    use Multitenantable;

    protected $fillable = ['organization_id', 'type', 'entity_id', 'key', 'value', 'options'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
