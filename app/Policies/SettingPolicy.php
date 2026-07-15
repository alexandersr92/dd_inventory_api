<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        $key = request()->query('key');
        $publicKeys = [
            'usd_exchange_rate',
            'cash_control_mode',
            'closing_count_type',
            'cash_assignment_mode',
            'carry_over_balance',
            'seller_login_mode'
        ];
        if (in_array($key, $publicKeys)) {
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
