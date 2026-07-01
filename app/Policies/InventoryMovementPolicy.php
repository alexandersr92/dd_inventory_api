<?php

namespace App\Policies;

use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('movement.index') || $user->hasPermissionTo('inventory.movement');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('movement.index') || $user->hasPermissionTo('inventory.movement');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('movement.store') || $user->hasPermissionTo('inventory.movement');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('movement.delete') || $user->hasPermissionTo('inventory.movement');
    }
}
