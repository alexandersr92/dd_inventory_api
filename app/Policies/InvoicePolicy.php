<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('invoice.index');
    }

    public function view(User $user, ?Invoice $invoice = null): bool
    {
        return $user->hasPermissionTo('invoice.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('invoice.store');
    }

    public function update(User $user, ?Invoice $invoice = null): bool
    {
        return $user->hasPermissionTo('invoice.store') || $user->hasPermissionTo('invoice.update');
    }

    public function delete(User $user, ?Invoice $invoice = null): bool
    {
        return $user->hasPermissionTo('invoice.delete') || $user->hasPermissionTo('invoice.store');
    }
}
