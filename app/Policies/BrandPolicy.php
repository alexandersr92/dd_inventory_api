<?php

namespace App\Policies;

use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('brand.index') || $user->hasPermissionTo('product.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('brand.index') || $user->hasPermissionTo('product.index');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('brand.store') || $user->hasPermissionTo('product.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('brand.update') || $user->hasPermissionTo('product.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('brand.delete') || $user->hasPermissionTo('product.delete');
    }
}
