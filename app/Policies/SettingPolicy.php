<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        $key = request()->query('key');
        if ($key === 'usd_exchange_rate' || $key === 'cash_control_mode') {
            return true;
        }
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
