<?php

namespace App\Contracts;

use App\Models\CustomJobOrder;
use App\Models\CustomJobOrderRevision;
use App\Models\User;

/** Owning plans 12/14 bind a coordinator. All writes must use the caller's tenant transaction; never independently commit cash/documents. */
interface CustomJobOrderRevisionFinancial
{
    public function preview(User $user, CustomJobOrder $order, array $commercial): array;

    public function apply(User $user, CustomJobOrder $order, CustomJobOrderRevision $revision): void;
}
