<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
class Setting extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = ['organization_id', 'type', 'entity_id', 'key', 'value'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
