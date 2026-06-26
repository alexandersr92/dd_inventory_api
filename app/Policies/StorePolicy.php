<?php

namespace App\Policies;

use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('store.index') || $user->stores()->exists();
    }

    public function view(User $user, \App\Models\Store $store): bool
    {
        return $user->hasPermissionTo('store.show') || $user->stores()->where('store_id', $store->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('store.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('store.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('store.delete');
    }
}
