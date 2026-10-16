<?php

namespace App\Services;

use App\Contracts\CustomJobOrderRevisionFinancial;
use App\Models\CustomJobOrder;
use App\Models\CustomJobOrderRevision;
use App\Models\User;
use App\Models\Invoice;

final class CustomJobPaymentRevisionFinancial implements CustomJobOrderRevisionFinancial
{
    public function preview(User $user, CustomJobOrder $order, array $commercial): array
    {
        $invoice = Invoice::where('custom_job_order_id', $order->id)
            ->whereNotIn('invoice_status', ['proforma', 'canceled', 'cancelled'])->first();

        return ['payments' => app(CustomJobPayments::class)->summary($order, $commercial),
            'billing' => $invoice ? ['editable' => false, 'reason' => 'La factura final requiere anulación o reemplazo documental.', 'invoice_id' => $invoice->id] : ['editable' => true]];
    }

    public function apply(User $user, CustomJobOrder $order, CustomJobOrderRevision $revision): void
    {
        // The balance is derived from the latest commercial total and immutable payments.
        // No duplicate credit, income or financial movement is needed on a revision.
    }
}
