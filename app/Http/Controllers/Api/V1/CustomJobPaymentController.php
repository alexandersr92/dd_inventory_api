<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobOrders;
use App\Services\CustomJobPayments;
use Illuminate\Http\Request;

final class CustomJobPaymentController extends Controller
{
    public function show(Request $r, string $id, CustomJobOrders $orders, CustomJobPayments $payments)
    {
        return response()->json(['data' => $payments->view($r->user(), $orders->accessible($r->user(), $id))])->header('Cache-Control', 'no-store');
    }

    public function record(Request $r, string $id, CustomJobPayments $payments, CustomJobOrders $orders)
    {
        $kind = $r->route('kind');
        $data = $r->validate([
            'version' => 'required|integer|min:1', 'request_id' => 'required|uuid',
            'amount' => 'required|numeric|gt:0|max:1000000000|decimal:0,2', 'currency' => 'required|in:NIO,USD',
            'cash_session_id' => 'nullable|uuid', 'source_payment_id' => $kind === 'refund' ? 'required|uuid' : 'prohibited',
            'reason' => 'required|string|max:1000', 'payment_method' => 'required|in:CASH,TRANSFER,CARD,MULTIPLE',
            'payment_metadata' => 'sometimes|array:payments,paid_in_nio,paid_in_usd,paid_nio,paid_usd,bank,reference,card_brand,card_last_four',
            'payment_metadata.payments' => 'sometimes|array|min:1|max:10',
            'payment_metadata.payments.*' => 'array:method,amount,paid_in_nio,paid_in_usd,paid_nio,paid_usd,bank,reference,card_brand,card_last_four',
        ]);
        if (trim($data['reason']) === '') {
            abort(422, 'Indica el motivo del cobro/devolución.');
        }
        $data['version'] = (int) $data['version'];
        $order = $payments->record($r->user(), $id, $data, $kind);

        return response()->json(['data' => $orders->representation($r->user(), $order), 'payments' => $payments->view($r->user(), $order)])->header('Cache-Control', 'no-store');
    }
}
