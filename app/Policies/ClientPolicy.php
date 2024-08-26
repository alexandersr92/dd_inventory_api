<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Create a new policy instance.
     */
    public function index(User $user, Client $client,): bool
    {

        return true;
    }
}
