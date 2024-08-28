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
        //usuario tiene permiso para ver todos los clientes

        return true;
    }
}
