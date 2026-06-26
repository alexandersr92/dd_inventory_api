<?php

namespace App\Policies;

use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('inventory.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('inventory.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('inventory.delete');
    }
}
