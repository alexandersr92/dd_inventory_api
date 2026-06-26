<?php

namespace App\Policies;

use App\Models\User;

class PurchasesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchase.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('purchase.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchase.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('purchase.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('purchase.delete');
    }
}
