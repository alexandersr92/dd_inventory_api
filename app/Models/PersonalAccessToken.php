<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'central';
}
