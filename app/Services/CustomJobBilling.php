<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\CustomJobOrder;
use App\Models\CustomJobPayment;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Store;
use App\Models\User;
use App\Support\CustomJobInvoiceDocument;
use App\Support\CustomJobMath as Math;
use Illuminate\Support\Facades\DB;

final class CustomJobBilling
{
    public function __construct(
        private CustomJobOrders $orders,
        private CustomJobDeliveries $deliveries,
        private CustomJobPayments $payments
    ) {}

    public function view(User $user, CustomJobOrder $order): array
    {
        $delivery = $this->deliveries->view($order);
        $invoice = Invoice::where('custom_job_order_id', $order->id)->with(['invoiceDetails', 'client', 'seller'])->first();
        $proforma = Invoice::where('custom_job_quote_id', $order->quote_id)->first();
        $activeFinal = $invoice && ! in_array($invoice->invoice_status, ['proforma', 'canceled', 'cancelled'], true);
        $available = $order->status === 'confirmed' && $delivery['can_invoice'] && ! $activeFinal;

        return [
            'available' => $available,
            'reason' => $order->status !== 'confirmed' ? 'El pedido no está confirmado.' : ($delivery['can_invoice'] ? ($activeFinal ? 'La factura final ya existe.' : null) : 'Completa la entrega neta de todos los artículos vigentes.'),
            'proforma' => $proforma ? CustomJobInvoiceDocument::data($proforma) : null,
            'final' => $invoice && $activeFinal ? CustomJobInvoiceDocument::data($invoice) : null,
            'payments' => $this->payments->summary($order),
        ];
    }

    public function issue(User $user, string $id, int $version): Invoice
    {
        return DB::transaction(function () use ($user, $id, $version) {
            $read = $this->orders->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->orders->accessible($user, $id, true);
            abort_unless($order->version === $version, 409, 'El pedido cambió. Actualiza antes de facturar.');
            abort_unless($order->status === 'confirmed', 409, 'Solo se factura un pedido confirmado.');

            $existing = Invoice::where('custom_job_order_id', $order->id)->lockForUpdate()->first();
            if ($existing && ! in_array($existing->invoice_status, ['proforma', 'canceled', 'cancelled'], true)) {
                return $existing->load(['invoiceDetails', 'client', 'seller']);
            }

            $delivery = $this->deliveries->view($order);
            abort_unless($delivery['can_invoice'], 409, 'Completa la entrega neta de todos los artículos vigentes antes de facturar.');
            $invoice = $existing ?: Invoice::where('custom_job_quote_id', $order->quote_id)->lockForUpdate()->first();
            abort_unless($invoice, 409, 'La proforma del trabajo no existe. Emite la cotización antes de facturar.');

            $summary = $this->payments->summary($order);
            $pending = (float) $summary['pending'];
            if ($pending > 0 && ! $order->client_id) {
                abort(422, 'El pedido necesita un cliente para registrar el saldo pendiente como crédito.');
            }

            $rows = CustomJobPayment::where('order_id', $order->id)
                ->where('status', 'active')
                ->whereIn('kind', ['payment', 'refund'])
                ->lockForUpdate()->get();
            foreach ($rows as $row) {
                abort_if($row->applied_invoice_id && $row->applied_invoice_id !== $invoice->id, 409, 'Un cobro ya fue aplicado a otra factura.');
            }

            $commercial = $order->commercial;
            $activeItems = $order->items()->where('active', true)->orderBy('position')->get();
            $invoice->fill([
                'custom_job_order_id' => $order->id,
                'custom_job_quote_id' => $order->quote_id,
                'custom_job_revision_id' => $order->quote_revision_id,
                'custom_job_commercial' => $commercial,
                'client_id' => $order->client_id,
                'client_name' => $order->client_name,
                'invoice_date' => now()->toDateString(),
                'invoice_note' => $commercial['note'] ?? null,
                'total' => $activeItems->sum(fn ($item) => (float) $item->quantity),
                'discount' => $commercial['totals']['discount'] ?? 0,
                'tax' => $commercial['totals']['tax'] ?? 0,
                'grand_total' => $commercial['totals']['total'] ?? 0,
                'invoice_type' => $pending > 0 ? 'credit' : 'cash',
                'invoice_status' => $pending > 0 ? 'credit' : 'completed',
                'payment_method' => $this->paymentMethod($rows),
                'payment_date' => now()->toDateString(),
                'paid_in_nio' => $summary['net_paid_nio'],
                'paid_in_usd' => $this->paidUsd($rows),
                'exchange_rate' => $commercial['exchange_rate'] ?? 1,
                'cash_session_id' => null,
                'payment_metadata' => ['custom_job' => true, 'payment_ids' => $rows->pluck('id')->values()->all()],
                'source' => 'ADMIN',
            ]);
            $invoice->save();
            $invoice->invoiceDetails()->delete();
            foreach ($activeItems as $item) {
                $line = $item->commercial;
                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'line_type' => 'custom_job',
                    'custom_job_order_item_id' => $item->id,
                    'custom_job_description' => $item->name,
                    'custom_job_snapshot' => $item->composition,
                    'product_id' => null,
                    'inventory_id' => null,
                    'quantity' => $item->quantity,
                    'price' => $line['unit_price'] ?? 0,
                    'cost' => $item->composition['cost'] ?? 0,
                    'total' => $line['price'] ?? 0,
                    'discount' => $line['discount'] ?? 0,
                    'tax' => 0,
                    'grand_total' => $line['net_price'] ?? ($line['price'] ?? 0),
                    'sort_order' => $item->position,
                ]);
            }

            foreach ($rows as $row) {
                $row->applied_invoice_id = $invoice->id;
                $row->applied_at = now('UTC');
                $row->save();
            }

            $credit = $invoice->credit()->lockForUpdate()->first();
            if ($pending > 0) {
                $credit ??= new Credit;
                $credit->fill(['user_id' => $user->id, 'organization_id' => $order->organization_id, 'store_id' => $order->store_id,
                    'client_id' => $order->client_id, 'invoice_id' => $invoice->id, 'total' => $commercial['totals']['total'], 'debt' => $pending, 'credit_status' => 'active']);
                $credit->save();
            } elseif ($credit) {
                $credit->debt = 0;
                $credit->credit_status = 'paid';
                $credit->save();
            }

            return $invoice->load(['invoiceDetails', 'client', 'seller']);
        });
    }

    public function releasePaymentApplications(Invoice $invoice): void
    {
        CustomJobPayment::where('applied_invoice_id', $invoice->id)->update(['applied_invoice_id' => null, 'applied_at' => null]);
    }

    private function paymentMethod($rows): string
    {
        $methods = $rows->pluck('payment_method')->filter()->unique()->values();

        return $methods->count() === 1 ? $methods->first() : ($methods->isEmpty() ? 'CASH' : 'MULTIPLE');
    }

    private function paidUsd($rows): float
    {
        $value = Math::of(0);
        foreach ($rows as $row) {
            if ($row->kind === 'payment' && $row->currency === 'USD') {
                $value = $value->plus(Math::of($row->amount));
            } elseif ($row->kind === 'refund' && $row->currency === 'USD') {
                $value = $value->minus(Math::of($row->amount));
            }
        }

        return Math::number($value, 2);
    }
}
