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
        // Organization owner always has full access
        $isOwner = $user->organization && $user->id === $user->organization->owner_id;
        return $isOwner || $user->hasPermissionTo('store.store');
    }

    public function update(User $user): bool
    {
        $isOwner = $user->organization && $user->id === $user->organization->owner_id;
        return $isOwner || $user->hasPermissionTo('store.update');
    }

    public function delete(User $user): bool
    {
        $isOwner = $user->organization && $user->id === $user->organization->owner_id;
        return $isOwner || $user->hasPermissionTo('store.delete');
    }
}
