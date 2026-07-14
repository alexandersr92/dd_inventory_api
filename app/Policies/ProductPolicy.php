<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('product.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('product.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('product.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('product.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('product.delete');
    }
}
