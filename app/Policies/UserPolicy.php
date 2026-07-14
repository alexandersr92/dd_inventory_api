<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('user.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('user.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('user.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('user.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('user.delete');
    }
}
