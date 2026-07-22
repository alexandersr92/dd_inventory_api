<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Support\Facades\Auth;

class SubscriptionInvoiceController extends Controller
{
    /** Facturas de suscripción emitidas a la organización del cliente. */
    public function index()
    {
        $orgId = Auth::user()->organization_id;

        $invoices = SubscriptionInvoice::where('organization_id', $orgId)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn ($i) => [
                'id'           => $i->id,
                'number'       => $i->number,
                'concept'      => $i->concept,
                'amount'       => $i->amount,
                'currency'     => $i->currency,
                'issued_at'    => $i->issued_at,
                'period_start' => $i->period_start,
                'period_end'   => $i->period_end,
            ]);

        return response()->json(['data' => $invoices]);
    }

    /** Descarga el PDF de una factura propia. */
    public function download($id)
    {
        $orgId = Auth::user()->organization_id;

        $invoice = SubscriptionInvoice::where('id', $id)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        return response(SubscriptionInvoiceService::renderPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . SubscriptionInvoiceService::filename($invoice) . '"',
        ]);
    }
}
