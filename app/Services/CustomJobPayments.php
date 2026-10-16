<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\CustomJobOrder;
use App\Models\CustomJobPayment as Payment;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\CustomJobMath as M;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomJobPayments
{
    public function __construct(private CustomJobOrders $orders, private CustomJobCalculationContext $context) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }

    private function setting(string $key, string $fallback): string
    {
        return Setting::where('key', $key)->where('type', 'global')->value('value') ?? $fallback;
    }

    private function money($value): float
    {
        return M::number(M::of($value), 2);
    }

    private function base($value, string $currency, $rate): float
    {
        return $this->money(M::of($value)->multipliedBy(M::of($currency === 'USD' ? $rate : 1)));
    }

    private function decimal($value, bool $positive = false): float
    {
        if (! is_numeric($value) || ! preg_match('/^\d+(\.\d{1,2})?$/D', (string) $value) || (float) $value > 1000000000 || ($positive ? (float) $value <= 0 : (float) $value < 0)) {
            $this->fail('Usa importes válidos con hasta dos decimales.');
        }

        return (float) $value;
    }

    public function summary(CustomJobOrder $order, ?array $commercial = null): array
    {
        $c = $commercial ?? $order->commercial;
        $rows = Payment::where('order_id', $order->id)->where('status', 'active')->get();
        $paid = M::of(0);
        $refunded = M::of(0);
        foreach ($rows as $r) {
            if ($r->kind === 'payment') {
                $paid = $paid->plus(M::of($r->amount_nio));
            } else {
                $refunded = $refunded->plus(M::of($r->amount_nio));
            }
        }
        $net = $paid->minus($refunded);
        $total = $this->base($c['totals']['total'], $c['currency'], $c['exchange_rate']);
        $balance = M::of($total)->minus($net);
        $rate = $c['currency'] === 'USD' ? $c['exchange_rate'] : 1;

        return ['currency' => $c['currency'], 'exchange_rate' => (float) $c['exchange_rate'], 'total' => $c['totals']['total'], 'paid' => $this->money($paid->dividedBy(M::of($rate))), 'refunded' => $this->money($refunded->dividedBy(M::of($rate))), 'net_paid' => $this->money($net->dividedBy(M::of($rate))), 'balance' => $this->money($balance->dividedBy(M::of($rate))), 'pending' => $this->money(M::of(max(0, M::number($balance)))->dividedBy(M::of($rate))), 'credit' => $this->money(M::of(max(0, -M::number($balance)))->dividedBy(M::of($rate))), 'paid_nio' => $this->money($paid), 'refunded_nio' => $this->money($refunded), 'net_paid_nio' => $this->money($net), 'total_nio' => $total, 'balance_nio' => $this->money($balance)];
    }

    private function sessions(User $user, CustomJobOrder $order)
    {
        return CashSession::where('store_id', $order->store_id)->where('status', 'open')->when($user->id !== TenantManager::getTenant()->owner_id, fn ($q) => $q->where('user_id', $user->id));
    }

    public function view(User $user, CustomJobOrder $order): array
    {
        $rows = Payment::where('order_id', $order->id)->orderBy('sequence')->get();
        $events = $rows->map(function ($r) use ($rows) {
            $returned = $rows->where('source_payment_id', $r->id)->where('status', 'active')->sum('amount');

            return [...$r->only(['id', 'kind', 'status', 'source_payment_id', 'cash_session_id', 'user_id', 'currency', 'amount', 'amount_nio', 'exchange_rate', 'payment_method', 'payment_metadata', 'reason', 'occurred_at']), 'occurred_at' => str_replace(' ', 'T', $r->getRawOriginal('occurred_at')).'Z', 'refundable_amount' => $r->kind === 'payment' && $r->status === 'active' ? $this->money(M::of($r->amount)->minus(M::of($returned))) : 0];
        })->all();
        $accepted = json_decode($this->setting('accepted_currencies', '["NIO","USD"]'), true);
        if (! is_array($accepted)) {
            $accepted = array_map('trim', explode(',', $this->setting('accepted_currencies', 'NIO,USD')));
        }
        $context = ['accepted_currencies' => array_values(array_intersect(['NIO', 'USD'], $accepted)), 'usd_exchange_rate' => (float) $this->setting('usd_exchange_rate', '36.5')];

        return ['summary' => $this->summary($order), 'events' => $events, 'cash' => ['mode' => $this->setting('cash_control_mode', 'NONE'), 'sessions' => $this->sessions($user, $order)->get(['id', 'cash_register_name', 'user_id', 'opened_at'])->toArray()], 'accepted_currencies' => $context['accepted_currencies'], 'current_exchange_rate' => $context['usd_exchange_rate'], 'annulment_available' => $order->status === 'confirmed' && $rows->contains(fn ($row) => $row->kind === 'payment' && $row->status === 'active')];
    }

    /** Normalize existing billing metadata: component amounts are NIO; cash received minus NIO change. */
    private function metadata(array $input, float $amountNio, float $rate, string $kind): array
    {
        $method = $input['payment_method'];
        $meta = $input['payment_metadata'] ?? [];
        $parts = $method === 'MULTIPLE' ? ($meta['payments'] ?? []) : [[...$meta, 'method' => $method, 'amount' => $amountNio]];
        if (! is_array($parts) || ! count($parts) || count($parts) > 10) {
            $this->fail('Indica los métodos del pago mixto.');
        }
        $result = [];
        $sum = M::of(0);
        foreach ($parts as $p) {
            $m = $p['method'] ?? '';
            if (! in_array($m, ['CASH', 'TRANSFER', 'CARD'], true)) {
                $this->fail('Método de cobro no válido.');
            }
            $a = $this->decimal($p['amount'] ?? 0, true);
            $row = ['method' => $m, 'amount' => $a];
            foreach (['reference', 'bank', 'card_brand', 'card_last_four'] as $key) {
                if (isset($p[$key])) {
                    if (! is_string($p[$key]) || strlen($p[$key]) > 120) {
                        $this->fail('Metadatos de cobro no válidos.');
                    } $row[$key] = trim($p[$key]);
                }
            }
            if ($m === 'TRANSFER' && (empty($row['bank']) || empty($row['reference']))) {
                $this->fail('Transferencia requiere banco y referencia.');
            }
            if ($m === 'CARD' && (empty($row['reference']) || ! preg_match('/^\d{4}$/D', $row['card_last_four'] ?? ''))) {
                $this->fail('Tarjeta requiere referencia y últimos cuatro dígitos.');
            }
            if ($m === 'CASH') {
                $defaultNio = $input['currency'] === 'NIO' ? ($input['amount']) : 0;
                $defaultUsd = $input['currency'] === 'USD' ? ($input['amount']) : 0;
                $nio = $this->decimal($p['paid_in_nio'] ?? $p['paid_nio'] ?? ($method === 'MULTIPLE' ? $a : $defaultNio));
                $usd = $this->decimal($p['paid_in_usd'] ?? $p['paid_usd'] ?? ($method === 'MULTIPLE' ? 0 : $defaultUsd));
                $change = $this->money(M::of($nio)->plus(M::of($usd)->multipliedBy(M::of($rate)))->minus(M::of($a)));
                // The final USD refund settles the original rounded NIO amount exactly.
                // Record the cent rounding difference separately; it is not physical cash change.
                if ($kind === 'refund' && $method === 'CASH' && $input['currency'] === 'USD' && $nio === 0.0 && $usd === (float) $input['amount'] && abs($change) <= 0.01) {
                    $row['rounding_nio'] = $change;
                    $change = 0.0;
                }
                if ($change < 0) {
                    $this->fail('El efectivo recibido no cubre su importe.');
                }
                if ($kind === 'refund' && $change !== 0.0) {
                    $this->fail('En una devolución indica el efectivo exacto que sale, sin vuelto.');
                }
                $row += ['paid_in_nio' => $nio, 'paid_in_usd' => $usd, 'change_nio' => $change, 'exchange_rate' => $rate];
            }
            $sum = $sum->plus(M::of($a));
            $result[] = $row;
        }
        if ($this->money($sum) !== $amountNio) {
            $this->fail('La suma de métodos debe ser exactamente el importe aplicado en córdobas.');
        }

        return ['payments' => $result];
    }

    public function record(User $user, string $id, array $input, string $kind): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $id, $input, $kind) {
            $read = $this->orders->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->orders->accessible($user, $id, true);
            $payload = $input;
            unset($payload['version']);
            $hash = hash('sha256', json_encode([$kind, $payload], JSON_THROW_ON_ERROR));
            $existing = Payment::where('order_id', $id)->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($hash, $existing->request_hash), 409, 'La clave de operación ya contiene otro cobro/devolución.');

                return $order;
            }
            abort_unless($order->version === $input['version'], 409, 'El pedido cambió; actualiza antes de registrar dinero.');
            abort_unless($kind === 'refund' ? in_array($order->status, ['confirmed', 'cancelled', 'canceled'], true) : $order->status === 'confirmed', 409, 'El estado del pedido no permite este cobro/devolución.');
            abort_if(Invoice::where('custom_job_quote_id', $order->quote_id)->where(fn ($q) => $q->where('invoice_type', '!=', 'proforma')->orWhere('invoice_status', '!=', 'proforma'))->exists(), 409, 'El pedido facturado requiere integrar el flujo documental del plan 14.');
            $source = null;
            if ($kind === 'refund') {
                $source = Payment::where('order_id', $id)->where('kind', 'payment')->where('status', 'active')->findOrFail($input['source_payment_id']);
            }
            $currency = $input['currency'];
            if ($source && $currency !== $source->currency) {
                $this->fail('Devuelve en la moneda del cobro original.');
            }
            $context = $source ? ['usd_exchange_rate' => (float) $source->exchange_rate, 'accepted_currencies' => []] : $this->context->commercial($this->context->store($user, $order->store_id), $currency);
            if (! $source && ! in_array($currency, $context['accepted_currencies'], true)) {
                $this->fail('Moneda desactivada en Facturación.');
            }
            $rate = (float) ($source?->exchange_rate ?? $context['usd_exchange_rate']);
            $amount = $this->decimal($input['amount'], true);
            $amountNio = $this->base($amount, $currency, $rate);
            if ($amountNio <= 0) {
                $this->fail('Importe demasiado pequeño.');
            }
            $s = $this->summary($order);
            if ($kind === 'payment' && $amountNio > max(0, $s['balance_nio'])) {
                $this->fail('El cobro supera el saldo pendiente del pedido.');
            }
            if ($source) {
                $refunds = Payment::where('source_payment_id', $source->id)->where('status', 'active')->get();
                $returned = $refunds->sum('amount');
                if (M::of($amount)->isEqualTo(M::of($source->amount)->minus(M::of($returned)))) {
                    $amountNio = $this->money(M::of($source->amount_nio)->minus(M::of($refunds->sum('amount_nio'))));
                }
                if (M::of($amount)->isGreaterThan(M::of($source->amount)->minus(M::of($returned)))) {
                    $this->fail('La devolución supera lo disponible del cobro original.');
                }
                $credit = in_array($order->status, ['canceled', 'cancelled'], true) ? $s['net_paid_nio'] : max(0, -$s['balance_nio']);
                if ($amountNio > $credit) {
                    $this->fail('Solo puedes devolver el saldo a favor o resolver dinero de un pedido cancelado.');
                }
            }
            $session = null;
            if (! empty($input['cash_session_id'])) {
                $session = $this->sessions($user, $order)->lockForUpdate()->find($input['cash_session_id']);
                abort_unless($session, 422, 'Selecciona una caja abierta y autorizada de la sucursal del pedido.');
            } elseif ($this->setting('cash_control_mode', 'NONE') === 'STRICT') {
                $this->fail('El control estricto requiere una sesión de caja abierta.');
            }
            $meta = $this->metadata($input, $amountNio, $rate, $kind);
            Payment::create(['order_id' => $id, 'sequence' => (Payment::where('order_id', $id)->max('sequence') ?? 0) + 1, 'source_payment_id' => $source?->id, 'cash_session_id' => $session?->id, 'user_id' => $user->id, 'kind' => $kind, 'request_id' => $input['request_id'], 'request_hash' => $hash, 'currency' => $currency, 'amount' => $amount, 'amount_nio' => $amountNio, 'exchange_rate' => $rate, 'payment_method' => $input['payment_method'], 'payment_metadata' => $meta, 'reason' => $input['reason'], 'occurred_at' => now('UTC')]);
            $order->version++;
            $order->save();

            return $order;
        });
    }

    /** Additive to the existing cash-session calculator, never manual editable cash movements. */
    public static function sessionTotals(string $sessionId): array
    {
        $t = ['cash_nio' => 0.0, 'cash_usd' => 0.0, 'transfer' => 0.0, 'card' => 0.0];
        foreach (Payment::where('cash_session_id', $sessionId)->where('status', 'active')->get() as $r) {
            $sign = $r->kind === 'payment' ? 1 : -1;
            foreach ($r->payment_metadata['payments'] as $p) {
                if ($p['method'] === 'CASH') {
                    $t['cash_nio'] += $sign * ($p['paid_in_nio'] - $p['change_nio']);
                    $t['cash_usd'] += $sign * $p['paid_in_usd'];
                } else {
                    $t[$p['method'] === 'CARD' ? 'card' : 'transfer'] += $sign * $p['amount'];
                }
            }
        }

        return array_map(fn ($v) => round($v, 2), $t);
    }
}
