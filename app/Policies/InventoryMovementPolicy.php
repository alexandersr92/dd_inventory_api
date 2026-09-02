<?php

namespace App\Policies;

use App\Models\User;

class InventoryMovementPolicy
{
    /**
     * Safely check if a user has any of the given permissions without throwing if missing in DB.
     */
    private function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Permission not in DB yet, ignore and check next fallback
            }
        }
        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            'movement.index',
            'inventory.movement',
            'inventory.show',
            'inventory.index'
        ]);
    }

    public function view(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            'movement.index',
            'inventory.movement',
            'inventory.show',
            'inventory.index'
        ]);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            'movement.store',
            'inventory.movement',
            'inventory.store',
            'inventory.update'
        ]);
    }

    public function delete(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            'movement.delete',
            'inventory.movement',
            'inventory.delete',
            'inventory.update'
        ]);
    }
}
