<?php

namespace App\Policies;

use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('category.index') || $user->hasPermissionTo('product.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('category.index') || $user->hasPermissionTo('product.index');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('category.store') || $user->hasPermissionTo('product.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('category.update') || $user->hasPermissionTo('product.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('category.delete') || $user->hasPermissionTo('product.delete');
    }
}
