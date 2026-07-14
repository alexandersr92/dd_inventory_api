<?php

namespace App\Policies;

use App\Models\User;

class CreditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('credit.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('credit.show');
    }

    public function payment(User $user): bool
    {
        return $user->hasPermissionTo('credit.payment');
    }
}
