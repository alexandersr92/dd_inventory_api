<?php

namespace App\Policies;

use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('supplier.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('supplier.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('supplier.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('supplier.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('supplier.delete');
    }
}
