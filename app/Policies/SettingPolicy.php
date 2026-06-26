<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('setting.index');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('setting.store');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('setting.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('setting.delete');
    }
}
