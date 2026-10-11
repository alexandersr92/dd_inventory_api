<?php

namespace App\Contracts;

use App\Models\CustomJobOrder;
use App\Models\User;

/** Plan 08 binds an implementation. All reservation writes must use the caller's tenant connection/transaction. */
interface CustomJobReservation
{
    public function reserve(User $user, CustomJobOrder $order, array $selection = []): void;
}
