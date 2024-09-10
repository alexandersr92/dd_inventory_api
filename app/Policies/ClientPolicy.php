<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;

class ClientPolicy
{
    /**
     * Create a new policy instance.
     */
    public function index(User $user): bool
    {
        /*   if ($user->hasRole('Owner')) {
            return true;
        }

        if(user ){
        

        }

        */
        return true;
    }

    public function show(User $user): bool
    {
        /*  if ($user->hasPermissionTo('client.show')) {
            return true;
        } */

        return true;
    }
}
