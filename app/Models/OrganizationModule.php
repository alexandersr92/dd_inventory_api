<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\Uuids;

class OrganizationModule extends Pivot
{
    use Uuids;

    protected $connection = 'central';
    protected $table = 'organization_modules';
}
