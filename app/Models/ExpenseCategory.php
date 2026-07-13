<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\Uuids;
use App\Traits\Multitenantable;

class ExpenseCategory extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $fillable = [
        'name',
        'organization_id',
    ];
}
