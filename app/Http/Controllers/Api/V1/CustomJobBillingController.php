<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobBilling;
use App\Services\CustomJobOrders;
use App\Support\CustomJobInvoiceDocument;
use Illuminate\Http\Request;

final class CustomJobBillingController extends Controller
{
    public function show(Request $request, string $id, CustomJobOrders $orders, CustomJobBilling $billing)
    {
        return response()->json(['data' => $billing->view($request->user(), $orders->accessible($request->user(), $id))])
            ->header('Cache-Control', 'no-store');
    }

    public function issue(Request $request, string $id, CustomJobOrders $orders, CustomJobBilling $billing)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'request_id' => ['required', 'uuid']]);
        $invoice = $billing->issue($request->user(), $id, (int) $data['version']);
        $order = $orders->accessible($request->user(), $id);

        return response()->json([
            'data' => $orders->representation($request->user(), $order),
            'invoice' => CustomJobInvoiceDocument::data($invoice->load(['invoiceDetails', 'client', 'seller'])),
        ])->header('Cache-Control', 'no-store');
    }
}
