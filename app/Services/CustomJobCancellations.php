<?php

namespace App\Services;

use App\Models\CustomJobCancellation as Cancellation;
use App\Models\CustomJobOrder;
use App\Models\CustomJobPayment as Payment;
use App\Models\CustomJobWorkOrder as Work;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomJobCancellations
{
    public function __construct(private CustomJobOrders $orders, private CustomJobMaterials $materials, private CustomJobPayments $payments) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cancellation' => $message]);
    }

    private function hash(array $data): string
    {
        $sort = function (array $value) use (&$sort) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$part) {
                if (is_array($part)) {
                    $part = $sort($part);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));
    }

    private function invoice(CustomJobOrder $order): bool
    {
        return Invoice::where('custom_job_quote_id', $order->quote_id)
            ->whereNotIn('invoice_status', ['proforma', 'canceled', 'cancelled'])
            ->exists();
    }

    private function permitted(User $user, string $permission): bool
    {
        return $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', $permission))->exists();
    }

    private function itemView(User $user, CustomJobOrder $order): array
    {
        $delivery = app(CustomJobDeliveries::class)->view($order);

        return $order->items->map(function ($item) use ($delivery) {
            $row = collect($delivery['items'])->firstWhere('id', $item->id) ?? [];

            return [
                'id' => $item->id,
                'name' => $item->name,
                'ordered' => (int) $item->quantity,
                'manufactured' => (int) ($row['manufactured'] ?? 0),
                'produced' => (int) ($row['produced'] ?? 0),
                'delivered_net' => (int) ($row['delivered_net'] ?? 0),
                'pending' => (int) ($row['pending'] ?? (int) $item->quantity),
                'available_finished_return' => (int) max(0, (int) ($row['delivered_net'] ?? 0)),
            ];
        })->all();
    }

    private function materialView(User $user, CustomJobOrder $order): array
    {
        $view = $this->materials->view($user, $order);

        return collect($view['allocations'])->map(function (array $row) {
            $remaining = max(0, (float) $row['remaining']);
            $sources = collect($row['events'])->where('kind', 'consume')->map(function (array $event) use ($row) {
                $recovered = collect($row['events'])->where('kind', 'recover')->where('source_event_id', $event['id'])->sum('quantity');

                return ['source_event_id' => $event['id'], 'consumed' => (float) $event['quantity'], 'recovered' => (float) $recovered, 'available' => max(0, (float) $event['quantity'] - (float) $recovered)];
            })->filter(fn (array $source) => $source['available'] > 0)->values()->all();
            $recoverable = collect($sources)->sum('available');

            return [
                'allocation_id' => $row['id'],
                'item_id' => $row['item_id'],
                'material_index' => $row['material_index'],
                'name' => $row['name'],
                'inventory_detail_id' => $row['inventory_detail_id'],
                'assigned' => (float) $row['assigned'],
                'used' => (float) $row['used'],
                'returned' => (float) $row['returned'],
                'remaining' => $remaining,
                'recoverable' => $recoverable,
                'max_return' => $remaining,
                'proposed_return' => $remaining,
                'recovery_sources' => $sources,
                'return_reason' => $remaining > 0 ? 'Material apartado sin consumir' : 'Material consumido; requiere recuperación explícita',
            ];
        })->values()->all();
    }

    public function preview(User $user, string $id, array $input): array
    {
        $order = $this->orders->accessible($user, $id);
        $operation = $input['operation'] ?? 'cancel';
        abort_unless(in_array($order->status, ['confirmed'], true), 409, 'Solo se puede cancelar un pedido confirmado vigente.');
        abort_if($this->invoice($order), 409, 'El pedido tiene factura final; resuélvelo desde el flujo documental del plan 14.');
        abort_unless(in_array($operation, ['cancel', 'annul_payment'], true), 422, 'Operación de cancelación inválida.');
        if ($operation === 'annul_payment') {
            abort_unless($this->permitted($user, 'custom_job.annul_payment'), 403, 'Anular cobros requiere el permiso financiero y de cancelación.');
        }
        $payment = null;
        if ($operation === 'annul_payment') {
            $payment = Payment::where('order_id', $id)->where('kind', 'payment')->where('status', 'active')->findOrFail($input['payment_id'] ?? '');
            abort_if(Payment::where('source_payment_id', $payment->id)->where('status', 'active')->exists(), 409, 'El cobro ya tiene devoluciones activas; resuelve ese dinero antes de anularlo.');
        }
        $materials = $this->materialView($user, $order);
        $data = [
            'order_id' => $order->id,
            'operation' => $operation,
            'payment_id' => $payment?->id,
            'status_before' => $order->status,
            'status_after' => 'cancelled',
            'version' => $order->version,
            'items' => $this->itemView($user, $order),
            'materials' => $materials,
            'payments' => $this->payments->view($user, $order),
            'selected_payment' => $payment ? [...$payment->only(['id', 'amount', 'currency', 'amount_nio', 'payment_method', 'occurred_at']), 'status' => $payment->status] : null,
            'invoice' => ['final_exists' => false, 'can_cancel' => true],
            'rules' => [
                'returns_are_selective' => true,
                'consumed_material_requires_recovery' => true,
                'annulment_cancels_order' => $operation === 'annul_payment',
                'money_is_not_returned_automatically' => true,
            ],
        ];
        $data['digest'] = $this->hash($data);

        return $data;
    }

    public function apply(User $user, string $id, array $input): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $id, $input) {
            $read = $this->orders->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->orders->accessible($user, $id, true);
            $payload = $input;
            unset($payload['version'], $payload['digest']);
            $hash = $this->hash($payload);
            $existing = Cancellation::where('order_id', $id)->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La clave de operación ya fue utilizada con otra cancelación.');

                return $order;
            }
            abort_unless($order->status === 'confirmed' && $order->version === (int) $input['version'], 409, 'El pedido cambió; vuelve a revisar la cancelación.');
            abort_if($this->invoice($order), 409, 'El pedido tiene factura final; resuélvelo desde el flujo documental del plan 14.');
            $operation = $input['operation'] ?? 'cancel';
            abort_unless(in_array($operation, ['cancel', 'annul_payment'], true), 422, 'Operación de cancelación inválida.');
            if ($operation === 'annul_payment') {
                abort_unless($this->permitted($user, 'custom_job.annul_payment'), 403, 'Anular cobros requiere el permiso financiero y de cancelación.');
            }
            $preview = $this->preview($user, $id, $input);
            abort_unless(hash_equals($preview['digest'], $input['digest']), 409, 'La revisión de cancelación cambió; vuelve a previsualizar.');
            $before = ['status' => $order->status, 'version' => $order->version, 'payment_id' => null];
            $payment = null;
            if ($operation === 'annul_payment') {
                $payment = Payment::where('order_id', $id)->where('kind', 'payment')->where('status', 'active')->lockForUpdate()->findOrFail($input['payment_id'] ?? '');
                abort_if(Payment::where('source_payment_id', $payment->id)->where('status', 'active')->exists(), 409, 'El cobro ya tiene devoluciones activas; resuelve ese dinero antes de anularlo.');
                $before['payment_id'] = $payment->id;
            }
            $returns = collect($input['returns'] ?? [])->filter(fn ($line) => (float) ($line['quantity'] ?? 0) > 0)->values();
            if ($returns->isNotEmpty()) {
                $version = $order->version;
                $this->materials->mutate($user, $id, $version, (string) Str::uuid(), 'return', $returns->map(fn ($line) => [
                    'allocation_id' => $line['allocation_id'],
                    'quantity' => $line['quantity'],
                    'inventory_detail_id' => $line['inventory_detail_id'] ?? null,
                ])->all());
                $order = $this->orders->accessible($user, $id, true);
            }
            $recoveries = collect($input['recoveries'] ?? [])->filter(fn ($line) => (float) ($line['quantity'] ?? 0) > 0)->values();
            if ($recoveries->isNotEmpty()) {
                $this->materials->mutate($user, $id, $order->version, (string) Str::uuid(), 'recover', $recoveries->map(fn ($line) => [
                    'allocation_id' => $line['allocation_id'],
                    'source_event_id' => $line['source_event_id'],
                    'quantity' => $line['quantity'],
                    'inventory_detail_id' => $line['inventory_detail_id'] ?? null,
                ])->all());
                $order = $this->orders->accessible($user, $id, true);
            }
            if ($payment) {
                $payment->status = 'voided';
                $payment->save();
            }
            $order->status = 'cancelled';
            $order->cancelled_at = now('UTC');
            $order->cancelled_by = $user->id;
            $order->cancellation_reason = trim($input['reason']);
            $order->version++;
            $order->save();
            $work = Work::where('order_id', $id)->lockForUpdate()->first();
            if ($work) {
                $work->cancelled_at = now('UTC');
                $work->cancelled_by = $user->id;
                $work->save();
            }
            Cancellation::create([
                'order_id' => $id,
                'payment_id' => $payment?->id,
                'user_id' => $user->id,
                'request_id' => $input['request_id'],
                'request_hash' => $hash,
                'operation' => $operation,
                'reason' => trim($input['reason']),
                'version_before' => $before['version'],
                'version_after' => $order->version,
                'before_snapshot' => $before,
                'after_snapshot' => ['status' => $order->status, 'version' => $order->version, 'payment_id' => $payment?->id],
                'effects' => ['returns' => $returns->all(), 'recoveries' => $recoveries->all(), 'payment_voided' => $payment?->id, 'money_returned' => false],
                'occurred_at' => now('UTC'),
            ]);

            return $order;
        });
    }

    public function history(User $user, string $id): array
    {
        $this->orders->accessible($user, $id);

        return Cancellation::where('order_id', $id)->orderBy('created_at')->get()->map(fn ($row) => [
            ...$row->only(['id', 'operation', 'reason', 'payment_id', 'user_id', 'request_id', 'version_before', 'version_after', 'effects']),
            'occurred_at' => $row->occurred_at?->toISOString(),
        ])->all();
    }
}
