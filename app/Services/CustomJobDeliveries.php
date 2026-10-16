<?php

namespace App\Services;

use App\Models\CustomJobDeliveryEvent as Event;
use App\Models\CustomJobDeliveryLine as Line;
use App\Models\CustomJobOrder;
use App\Models\CustomJobOrderSurplusEvent;
use App\Models\CustomJobProductionEvent as Progress;
use App\Models\CustomJobWorkOrder as Work;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomJobDeliveries
{
    public function __construct(private CustomJobOrders $orders) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['delivery' => $message]);
    }

    private function history(string $id)
    {
        return Line::whereIn('event_id', Event::where('order_id', $id)->select('id'))->with('event')->get();
    }

    public function balance(CustomJobOrder $order, string $itemId): array
    {
        $rows = $this->history($order->id)->where('item_id', $itemId);
        $gross = 0;
        $returned = 0;
        $corrected = 0;
        $unavailable = 0;
        foreach ($rows as $l) {
            $kind = $l->event->kind;
            if ($kind === 'delivery') {
                $gross += $l->quantity;
            }
            if ($kind === 'correct_delivery') {
                $corrected += $l->quantity;
            }
            if ($kind === 'return' || $kind === 'correct_return') {
                $sign = $kind === 'return' ? 1 : -1;
                $returned += $sign * $l->quantity;
                if ($l->disposition !== 'ready') {
                    $unavailable += $sign * $l->quantity;
                }
            }
        }
        $work = Work::where('order_id', $order->id)->first();
        $manufactured = $work ? (int) Progress::where('work_order_id', $work->id)->where('item_id', $itemId)->sum('produced') : 0;
        $item = $order->items()->findOrFail($itemId);
        $net = $gross - $corrected - $returned;
        $surplus = (int) CustomJobOrderSurplusEvent::where('item_id', $itemId)->sum('quantity');
        $usable = $manufactured - $unavailable - $surplus;

        return ['surplus' => $surplus, 'manufactured' => $manufactured, 'produced' => $usable, 'unavailable_returned' => $unavailable, 'delivered_gross' => $gross, 'delivery_corrections' => $corrected,
            'returned' => $returned, 'delivered_net' => $net, 'pending' => (int) $item->quantity - $net, 'available' => max(0, min($usable, (int) $item->quantity) - $net)];
    }

    private function sourceState($rows, Line $source): array
    {
        $returned = 0;
        $correction = 0;
        foreach ($rows->where('source_line_id', $source->id) as $l) {
            if ($l->event->kind === 'return') {
                $undo = $rows->where('source_line_id', $l->id)->filter(fn ($r) => $r->event->kind === 'correct_return')->sum('quantity');
                $returned += $l->quantity - $undo;
            }
            if (in_array($l->event->kind, ['correct_delivery', 'correct_return'], true)) {
                $correction += $l->quantity;
            }
        }

        return ['returnable' => $source->quantity - $returned - $correction, 'correctable' => $source->quantity - $returned - $correction];
    }

    private function hash(array $input): string
    {
        unset($input['version']);
        $sort = function (array $a) use (&$sort) {
            if (! array_is_list($a)) {
                ksort($a);
            }foreach ($a as &$v) {
                if (is_array($v)) {
                    $v = $sort($v);
                }
            }

            return $a;
        };

        return hash('sha256', json_encode($sort($input), JSON_THROW_ON_ERROR));
    }

    public function record(User $user, string $id, string $kind, array $input): void
    {
        DB::transaction(function () use ($user, $id, $kind, $input) {
            $read = $this->orders->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->orders->accessible($user, $id, true);
            // Exclude version inside the payload as well: an exact replay can arrive with an obsolete version.
            $payload = $input;
            unset($payload['version']);
            $hash = $this->hash([$kind, $payload]);
            $existing = Event::where('order_id', $id)->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La solicitud ya se utilizó con otra entrega/devolución.');

                return;
            }
            abort_unless($order->version === $input['version'], 409, 'El pedido cambió. Recarga antes de registrar.');
            abort_unless($kind === 'delivery' ? $order->status === 'confirmed' : in_array($order->status, ['confirmed', 'cancelled'], true), 409, 'El estado del pedido no permite esta operación.');
            if ($kind !== 'delivery' && trim($input['note']) === '') {
                $this->fail('Indica el motivo de devolución o corrección.');
            }
            $at = $this->orders->localInstant($input['occurred_local'], $order->delivery_timezone);
            abort_if($at > now('UTC')->format('Y-m-d H:i:s'), 422, 'La entrega/devolución no puede tener una fecha futura.');
            $event = Event::create(['order_id' => $id, 'request_id' => $input['request_id'], 'request_hash' => $hash, 'kind' => $kind, 'occurred_local' => $input['occurred_local'],
                'timezone' => $order->delivery_timezone, 'occurred_at' => $at, 'user_id' => $user->id, 'note' => $input['note']]);
            foreach ($input['lines'] as $line) {
                $source = null;
                $disposition = null;
                if ($kind === 'delivery') {
                    $item = $order->items()->findOrFail($line['item_id']);
                    $quantity = $line['quantity'];
                    if ($quantity > $this->balance($order, $item->id)['available']) {
                        $this->fail('La entrega excede producción útil disponible o cantidad vigente.');
                    }
                } else {
                    $rows = $this->history($id);
                    $source = $rows->firstWhere('id', $line['source_line_id']);
                    abort_unless($source, 404);
                    $expected = $kind === 'correct_return' ? 'return' : 'delivery';
                    abort_unless($source->event->kind === $expected, 422, 'Selecciona una línea original válida.');
                    abort_if($at < $source->event->occurred_at, 422, 'La fecha no puede ser anterior al registro de origen.');
                    $item = $order->items()->findOrFail($source->item_id);
                    $state = $this->sourceState($rows, $source);
                    if ($kind === 'return') {
                        $quantity = $line['quantity'];
                        $disposition = $line['disposition'];
                        if ($quantity > $state['returnable']) {
                            $this->fail('La devolución excede lo entregado neto de su línea de origen.');
                        }
                    } else {
                        if ($kind === 'correct_return' && $rows->where('source_line_id', $source->id)->contains(fn ($r) => $r->event->kind === $kind)) {
                            $this->fail('Esta línea ya fue corregida.');
                        }
                        $quantity = $state['correctable'];
                        $disposition = $source->disposition;
                        if ($quantity <= 0) {
                            $this->fail('La línea no tiene cantidad pendiente de corregir.');
                        }
                    }
                }
                Line::create(['event_id' => $event->id, 'item_id' => $item->id, 'quantity' => $quantity, 'source_line_id' => $source?->id, 'disposition' => $disposition]);
                $b = $this->balance($order, $item->id);
                if ($b['delivered_net'] < 0 || $b['returned'] < 0 || $b['unavailable_returned'] < 0 || $b['delivered_net'] > min($b['produced'], (int) $item->quantity)
                   || $b['manufactured'] > (int) $item->quantity + $b['unavailable_returned'] + $b['surplus']) {
                    $this->fail('La corrección deja cantidades inconsistentes con producción, entregas o reemplazos vigentes.');
                }
            }
            $order->version++;
            $order->save();
        });
    }

    public function view(CustomJobOrder $order): array
    {
        $items = $order->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'active' => $i->active, 'ordered' => (int) $i->quantity, ...$this->balance($order, $i->id)])->all();
        $complete = count($items) > 0 && collect($items)->every(fn ($i) => $i['pending'] === 0);
        $rows = $this->history($order->id);
        $events = Event::where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get()->map(function ($e) use ($rows) {
            return [...$e->only(['id', 'kind', 'occurred_local', 'timezone', 'user_id', 'note', 'created_at']), 'occurred_at' => str_replace(' ', 'T', $e->occurred_at).'Z',
                'lines' => $rows->where('event_id', $e->id)->map(fn ($l) => [...$l->only(['id', 'item_id', 'quantity', 'source_line_id', 'disposition']),
                    ...($e->kind === 'delivery' || $e->kind === 'return' ? $this->sourceState($rows, $l) : [])])->values()->all()];
        })->all();

        $finalInvoice = Invoice::where('custom_job_order_id', $order->id)
            ->whereNotIn('invoice_status', ['proforma', 'canceled', 'cancelled'])->exists();

        return ['items' => $items, 'events' => $events, 'status' => $complete ? 'complete' : (collect($items)->some(fn ($i) => $i['delivered_net'] > 0) ? 'partial' : 'pending'),
            'can_invoice' => $order->status === 'confirmed' && $complete && ! $finalInvoice, 'can_deliver' => $order->status === 'confirmed' && ! $finalInvoice];
    }
}
