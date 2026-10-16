<?php

namespace App\Services;

use App\Contracts\CustomJobOrderRevisionFinancial;
use App\Models\CustomJobOrder;
use App\Models\CustomJobOrderRevision;
use App\Models\User;

final class CustomJobPaymentRevisionFinancial implements CustomJobOrderRevisionFinancial
{
    public function preview(User $user, CustomJobOrder $order, array $commercial): array
    {
        return ['payments' => app(CustomJobPayments::class)->summary($order, $commercial), 'billing' => null];
    }

    public function apply(User $user, CustomJobOrder $order, CustomJobOrderRevision $revision): void
    {
        // The balance is derived from the latest commercial total and immutable payments.
        // No duplicate credit, income or financial movement is needed on a revision.
    }
}
