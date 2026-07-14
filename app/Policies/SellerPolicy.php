<?php

namespace App\Policies;

use App\Models\User;

class SellerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('seller.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('seller.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('seller.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('seller.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('seller.delete');
    }
}
