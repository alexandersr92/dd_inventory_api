<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('role.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('role.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('role.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('role.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('role.delete');
    }
}
