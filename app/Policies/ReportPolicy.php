<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('report.index');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('report.store');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('report.delete');
    }

    public function download(User $user): bool
    {
        return $user->hasPermissionTo('report.download');
    }
}
