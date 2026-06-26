<?php

namespace App\Policies;

use App\Models\User;

class ClientPolicy
{
    /**
     * Determine if the user can view the list of clients.
     */
    public function index(User $user): bool
    {
        return $user->hasPermissionTo('client.index');
    }

    /**
     * Determine if the user can view a client.
     */
    public function show(User $user): bool
    {
        return $user->hasPermissionTo('client.show');
    }

    /**
     * Determine if the user can create a client.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('client.store');
    }

    /**
     * Determine if the user can update a client.
     */
    public function update(User $user): bool
    {
        return $user->hasPermissionTo('client.update');
    }

    /**
     * Determine if the user can delete a client.
     */
    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('client.delete');
    }
}
