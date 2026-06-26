<?php

namespace App\Policies;

use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('invoice.index');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('invoice.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('invoice.store');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('invoice.delete');
    }
}
