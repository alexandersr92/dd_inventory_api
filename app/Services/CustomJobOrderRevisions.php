<?php

namespace App\Services;

use App\Contracts\CustomJobOrderRevisionFinancial;
use App\Models\CustomJobMaterialAllocation as Allocation;
use App\Models\CustomJobOrder;
use App\Models\CustomJobOrderItem as Item;
use App\Models\CustomJobOrderRevision as Revision;
use App\Models\CustomJobOrderSurplusEvent as Surplus;
use App\Models\CustomJobProductionEvent as Progress;
use App\Models\CustomJobWorkOrder as Work;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Support\CustomJobCalculationInput;
use App\Support\CustomJobMath as Math;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomJobOrderRevisions
{
    public function __construct(private CustomJobOrders $orders, private CustomJobCalculator $calculator, private CustomJobCalculationContext $context, private CustomJobMaterials $materials, private CustomJobDeliveries $deliveries) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }

    private function costs(User $user): bool
    {
        return $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
    }

    private function hash(array $data): string
    {
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

        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));
    }

    private function state(CustomJobOrder $order): array
    {
        return ['commercial' => $order->commercial, 'snapshot' => $order->snapshot, 'delivery_local' => $order->delivery_local, 'delivery_at' => $order->delivery_at, 'version' => $order->version,
            'items' => $order->items()->get()->map(fn ($i) => $i->only(['id', 'position', 'name', 'quantity', 'active', 'commercial', 'composition']))->all()];
    }

    public function history(User $user, CustomJobOrder $order): array
    {
        $costs = $this->costs($user);

        return Revision::where('order_id', $order->id)->orderByDesc('number')->get()->map(function ($r) use ($costs) {
            $clean = function (array $s) use ($costs) {
                if (! $costs) {
                    unset($s['snapshot']);
                    foreach ($s['items'] as &$i) {
                        unset($i['composition']);
                    }
                }

                return $s;
            };

            return [...$r->only(['id', 'number', 'user_id', 'reason', 'created_at']), 'before' => $clean($r->before), 'after' => $clean($r->after), 'excess' => Surplus::where('revision_id', $r->id)->get()->map(fn ($e) => $e->only(['id', 'item_id', 'quantity', 'disposition', 'source_event_id']))->all()];
        })->all();
    }

    private function restored(CustomJobOrder $order, array $input): array
    {
        $rows = [];
        $totals = [];
        foreach ($input['restore_excess'] ?? [] as $line) {
            $e = Surplus::whereIn('item_id', $order->items()->pluck('id'))->where('disposition', 'hold')->where('quantity', '>', 0)->findOrFail($line['source_event_id']);
            $used = -(int) Surplus::where('source_event_id', $e->id)->sum('quantity');
            $already = collect($rows)->where('source_event_id', $e->id)->sum('quantity');
            if ($line['quantity'] > $e->quantity - $used - $already) {
                $this->fail('El retorno de excedente supera el terminado disponible en resguardo.');
            }
            $rows[] = ['source_event_id' => $e->id, 'item_id' => $e->item_id, 'quantity' => $line['quantity']];
            $totals[$e->item_id] = ($totals[$e->item_id] ?? 0) + $line['quantity'];
        }

        return [$rows, $totals];
    }

    private function project(User $user, CustomJobOrder $order, array $input): array
    {
        abort_unless($order->status === 'confirmed' && $order->version === $input['version'], 409, 'Edita un pedido confirmado con su versión vigente.');
        $delivery = $input['delivery_local'];
        $utc = $this->orders->localInstant($delivery, $order->delivery_timezone);
        $current = $order->items()->get()->keyBy('id');
        $requested = [];
        $entries = [];
        $priceMethods = [];
        foreach ($input['items'] as $line) {
            if (isset($requested[$line['id']])) {
                $this->fail('Artículo repetido.');
            }$requested[$line['id']] = $line;
            $old = $current->get($line['id']);
            if (! $old && Item::withoutGlobalScopes()->whereKey($line['id'])->exists()) {
                $this->fail('Identificador de artículo no disponible.');
            }
            if (isset($line['composition']) && ! $this->costs($user)) {
                abort(403);
            }
            if ($line['quantity'] === 0) {
                if (! $old) {
                    $this->fail('Un nuevo artículo debe tener cantidad positiva.');
                }

                continue;
            }
            $base = $line['composition'] ?? ($old ? ['name' => $old->name, 'quantity' => $old->quantity, 'materials' => array_column($old->composition['materials'], 'input'),
                'operations' => array_column($old->composition['operations'] ?? [], 'input'), 'profit' => $old->composition['profit'] ?? ['method' => 'percent', 'value' => 30, 'scope' => 'batch']] : null);
            if (! $base) {
                $this->fail('Un nuevo artículo requiere composición.');
            }
            $base['name'] = $line['name'] ?? $base['name'];
            $base['quantity'] = $line['quantity'];
            $entries[] = $base;
            $priceMethods[] = $line;
        }
        if (! $entries) {
            $this->fail('La cancelación completa pertenece al flujo de cancelaciones; conserva al menos un artículo vigente.');
        }
        $calcInput = CustomJobCalculationInput::validate(['store_id' => $order->store_id, 'currency' => $input['currency'] ?? $order->commercial['currency'], 'articles' => $entries,
            'discount' => $input['discount'] ?? $order->snapshot['input']['discount'], 'tax_amount' => $input['tax_amount'] ?? $order->snapshot['input']['tax_amount']]);
        $context = $this->context->commercial($this->context->store($user, $order->store_id), $calcInput['currency']);
        $prices = [];
        foreach ($priceMethods as $i => $line) {
            $method = $line['price']['method'] ?? 'keep';
            $old = $current->get($line['id']);
            if ($method === 'explicit') {
                $prices[$i] = $line['price']['unit_price'] ?? null;
                if ($prices[$i] === null) {
                    $this->fail('Indica el nuevo precio unitario.');
                }
            } elseif ($method === 'keep' && $old) {
                $base = Math::of($old->commercial['unit_price'])->multipliedBy(Math::of(($old->commercial['currency'] ?? $order->commercial['currency']) === 'USD' ? ($old->commercial['exchange_rate'] ?? $order->commercial['exchange_rate']) : 1));
                $prices[$i] = Math::number($base->dividedBy(Math::of($calcInput['currency'] === 'USD' ? $context['usd_exchange_rate'] : 1)));
            }
        }
        $calc = $this->calculator->calculate($calcInput, $context, $this->context->inventories($user)->pluck('id')->all(), $prices);
        [$restores,$restored] = $this->restored($order, $input);
        $active = [];
        $comparisons = [];
        $newExcess = [];
        $needs = [];
        $materialRows = [];
        $allocations = Allocation::where('order_id', $order->id)->get();
        $work = Work::where('order_id', $order->id)->first();
        $progress = $work ? Progress::where('work_order_id', $work->id)->where('kind', 'progress')->get() : collect();
        // Initial basis for legacy production events predating plan 11; later events carry their own material basis.
        $first = Revision::where('order_id', $order->id)->orderBy('number')->first();
        $initial = collect($first?->before['items'] ?? $this->state($order)['items'])->keyBy('id');
        foreach ($current as $old) {
            $newQty = $requested[$old->id]['quantity'] ?? 0;
            $b = $this->deliveries->balance($order, $old->id);
            if ($newQty < $b['delivered_net']) {
                $this->fail('No reduzcas por debajo de entrega neta; resuelve devolución/corrección primero.');
            }
            $effective = $b['produced'] + ($restored[$old->id] ?? 0);
            $excess = max(0, $effective - $newQty);
            $newExcess[$old->id] = $excess;
            $comparisons[$old->id] = ['item_id' => $old->id, 'name' => $old->name, 'before' => (int) $old->quantity, 'after' => $newQty,
                'manufactured' => $b['manufactured'], 'produced' => $b['produced'], 'delivered' => $b['delivered_net'], 'pending' => max(0, $newQty - min($effective, $newQty)), 'excess' => $excess];
        }
        foreach ($calc['articles'] as $i => &$a) {
            $id = $priceMethods[$i]['id'];
            $old = $current->get($id);
            $b = $old ? $this->deliveries->balance($order, $id) : ['produced' => 0, 'manufactured' => 0, 'delivered_net' => 0];
            $useful = min($a['quantity'], $b['produced'] + ($restored[$id] ?? 0));
            $pending = $a['quantity'] - $useful;
            $previous = $old ? collect($old->composition['materials']) : collect();
            $slotRows = $previous->map(fn ($m, $k) => [...$m, 'slot' => $m['slot'] ?? $k])->keyBy('slot');
            foreach ($allocations->where('item_id', $id) as $alloc) {
                if (! $slotRows->has($alloc->material_index)) {
                    $slotRows->put($alloc->material_index, ['slot' => $alloc->material_index, 'product_id' => $alloc->product_id, 'base_unit' => $alloc->base_unit]);
                }
            }
            $next = ($slotRows->keys()->max() ?? -1) + 1;
            $taken = [];
            foreach ($a['materials'] as $j => &$m) {
                $slot = $slotRows->first(fn ($s) => ! isset($taken[$s['slot']]) && $s['product_id'] === $m['product_id'] && $s['base_unit'] === $m['base_unit'])['slot'] ?? $next++;
                $taken[$slot] = true;
                $m['slot'] = $slot;
                $per = Math::of($m['base_quantity'])->dividedBy(Math::of($a['quantity']));
                $used = Math::of(0);
                $remaining = Math::of(0);
                foreach ($allocations->where('item_id', $id)->where('material_index', $slot) as $alloc) {
                    $ab = $this->materials->balance($alloc);
                    $used = $used->plus(Math::of($ab['used']));
                    $remaining = $remaining->plus(Math::of($ab['remaining']));
                }
                $expectedSpent = Math::of(0);
                foreach ($progress->where('item_id', $id) as $event) {
                    $basis = $event->material_basis;
                    if ($basis === null) {
                        $historic = $initial->get($id)['composition']['materials'] ?? [];
                        $hm = collect($historic)->first(fn ($v, $k) => ($v['slot'] ?? $k) === $slot);
                        $rate = $hm ? Math::of($hm['base_quantity'])->dividedBy(Math::of($initial->get($id)['quantity'])) : Math::of(0);
                    } else {
                        $rate = Math::of($basis[$slot] ?? 0);
                    }
                    $expectedSpent = $expectedSpent->plus($rate->multipliedBy(Math::of($event->getRawOriginal('produced'))->plus(Math::of($event->getRawOriginal('discarded')))));
                }
                $prepared = $used->minus($expectedSpent);
                $previousInput = $slotRows->get($slot)['input'] ?? null;
                $newInput = $m['input'];
                if ($previousInput !== null) {
                    unset($previousInput['inventory_detail_id']);
                }
                unset($newInput['inventory_detail_id']);
                // A changed cut/format cannot treat previously transformed material as usable preparation.
                if ($previousInput === null || $previousInput != $newInput) {
                    $prepared = Math::of(0);
                }
                if ($prepared->isNegative()) {
                    $prepared = Math::of(0);
                }
                $needed = $per->multipliedBy(Math::of($pending))->minus($prepared);
                if ($needed->isNegative()) {
                    $needed = Math::of(0);
                }
                $needed = Math::of(Math::number($needed, 6));
                $target = $used->plus($needed);
                $excess = $remaining->minus($needed);
                if ($excess->isNegative()) {
                    $excess = Math::of(0);
                }
                $needs[$id.':'.$slot] = ['item_id' => $id, 'material_index' => $slot, 'target' => Math::number($target, 6), 'needed' => Math::number($needed, 6), 'remaining' => Math::number($remaining, 6), 'excess' => Math::number($excess, 6), 'origin' => $m['inventory_detail_id']];
            }unset($m);
            $active[$id] = ['id' => $id, 'position' => $i, 'name' => $a['name'], 'quantity' => $a['quantity'], 'composition' => $a];
            if (! $old) {
                $comparisons[$id] = ['item_id' => $id, 'name' => $a['name'], 'before' => 0, 'after' => $a['quantity'], 'manufactured' => 0, 'produced' => 0, 'delivered' => 0, 'pending' => $a['quantity'], 'excess' => 0];
            }
        }unset($a);
        foreach ($allocations as $alloc) {
            $key = $alloc->item_id.':'.$alloc->material_index;
            $ab = $this->materials->balance($alloc);
            $need = $needs[$key] ?? ['item_id' => $alloc->item_id, 'material_index' => $alloc->material_index, 'target' => $ab['used'], 'needed' => 0, 'remaining' => 0, 'excess' => 0, 'origin' => $alloc->inventory_detail_id];
            if (! isset($needs[$key])) {
                $need['remaining'] += $ab['remaining'];
                $need['excess'] += $ab['remaining'];
                $needs[$key] = $need;
            }
            $materialRows[] = [...$alloc->only(['id', 'item_id', 'material_index', 'name', 'inventory_name', 'inventory_detail_id', 'base_unit']), ...$ab, 'needed' => $need['needed'], 'max_return' => min($ab['remaining'], $need['excess'])];
        }
        // Removed material slots can have several origins; aggregate their remaining quantities once.
        foreach ($needs as $key => &$need) {
            if (! isset($active[$need['item_id']]) || ! collect($active[$need['item_id']]['composition']['materials'])->contains(fn ($m) => $m['slot'] === $need['material_index'])) {
                $rows = collect($materialRows)->filter(fn ($a) => $a['item_id'] === $need['item_id'] && $a['material_index'] === $need['material_index']);
                $need['remaining'] = $rows->sum('remaining');
                $need['excess'] = $need['remaining'];
            }
        }unset($need);
        $suggested = [];
        $left = array_map(fn ($n) => $n['excess'], $needs);
        foreach ($materialRows as &$row) {
            $key = $row['item_id'].':'.$row['material_index'];
            $row['max_return'] = min($row['remaining'], $needs[$key]['excess']);
            $row['proposed_return'] = min($row['remaining'], $left[$key]);
            $left[$key] = Math::number(Math::of($left[$key])->minus(Math::of($row['proposed_return'])), 6);
            $suggested[] = ['allocation_id' => $row['id'], 'quantity' => $row['proposed_return']];
        }unset($row);
        $public = $order->commercial;
        $public['articles'] = array_map(fn ($a) => array_intersect_key($a, array_flip(['name', 'quantity', 'unit_price', 'price', 'discount', 'net_price'])), $calc['articles']);
        $public['currency'] = $calcInput['currency'];
        $public['exchange_rate'] = $context['usd_exchange_rate'];
        $public['totals'] = array_intersect_key($calc['totals'], array_flip(['subtotal', 'discount', 'net', 'tax', 'total']));
        $financial = app()->bound(CustomJobOrderRevisionFinancial::class) ? app(CustomJobOrderRevisionFinancial::class)->preview($user, $order, $public) : ['payments' => null, 'billing' => null];
        if (($financial['billing']['editable'] ?? true) === false) {
            abort(409, $financial['billing']['reason'] ?? 'El documento emitido requiere corrección documental.');
        }
        if (($financial['billing']['editable'] ?? false) !== true && Invoice::where('custom_job_quote_id', $order->quote_id)->where(fn ($q) => $q->where('invoice_type', '!=', 'proforma')->orWhere('invoice_status', '!=', 'proforma'))->exists()) {
            abort(409, 'El documento emitido requiere integrar corrección del plan 14.');
        }
        $holds = Surplus::whereIn('item_id', $current->keys())->where('disposition', 'hold')->where('quantity', '>', 0)->get()->map(fn ($e) => [...$e->only(['id', 'item_id', 'quantity']), 'available' => $e->quantity + (int) Surplus::where('source_event_id', $e->id)->sum('quantity')])->filter(fn ($e) => $e['available'] > 0)->values()->all();
        $result = ['before' => $this->state($order), 'commercial' => $public, 'snapshot' => $calc['snapshot'], 'active' => $active, 'comparisons' => array_values($comparisons),
            'materials' => $materialRows, 'needs' => $needs, 'excess' => $newExcess, 'restore' => $restores, 'holds' => $holds, 'delivery_local' => $delivery, 'delivery_at' => $utc, 'financial' => $financial];
        $result['snapshot']['articles'] = $calc['articles'];
        $result['digest'] = $this->hash($result);

        return $result;
    }

    private function expose(User $user, array $p): array
    {
        $data = ['digest' => $p['digest'], 'before' => $p['before']['commercial'], 'after' => $p['commercial'], 'items' => $p['comparisons'], 'materials' => $p['materials'], 'holds' => $p['holds'], 'delivery_local' => $p['delivery_local'], 'financial' => $p['financial'],
            'additional' => array_values(array_filter($p['needs'], fn ($n) => $n['needed'] > $n['remaining']))];
        if ($this->costs($user)) {
            $data['snapshot'] = $p['snapshot'];
        }

        return $data;
    }

    public function preview(User $user, string $id, array $input): array
    {
        return $this->expose($user, $this->project($user, $this->orders->accessible($user, $id), $input));
    }

    public function apply(User $user, string $id, array $input): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $id, $input) {
            if (trim($input['reason']) === '') {
                $this->fail('Indica el motivo de la revisión.');
            }
            $read = $this->orders->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->orders->accessible($user, $id, true);
            $payload = $input;
            unset($payload['version']);
            $hash = $this->hash($payload);
            $existing = Revision::where('order_id', $id)->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La solicitud ya contiene otra revisión.');

                return $order;
            }
            $p = $this->project($user, $order, $input);
            abort_unless(hash_equals($p['digest'], $input['preview_digest']), 409, 'La vista previa cambió. Revisa de nuevo antes de confirmar.');
            $returns = $input['returns'];
            $sum = [];
            foreach ($returns as $line) {
                $a = collect($p['materials'])->firstWhere('id', $line['allocation_id']);
                abort_unless($a, 404);
                $key = $a['item_id'].':'.$a['material_index'];
                $sum[$key] = ($sum[$key] ?? Math::of(0))->plus(Math::of($line['quantity']));
            }
            foreach ($sum as $key => $n) {
                if ($n->isGreaterThan(Math::of($p['needs'][$key]['excess']))) {
                    $this->fail('La devolución libera material necesario para las piezas pendientes.');
                }
            }
            if (collect($returns)->some(fn ($l) => $l['quantity'] > 0)) {
                abort_unless($user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.return'))->exists(), 403);
            }
            foreach ($p['excess'] as $itemId => $qty) {
                if ($qty > 0 && ! isset($input['excess'][$itemId])) {
                    $this->fail('Selecciona destino del excedente terminado para cada artículo.');
                }
            }
            $revision = Revision::create(['order_id' => $id, 'number' => (Revision::where('order_id', $id)->max('number') ?? 0) + 1, 'request_id' => $input['request_id'], 'request_hash' => $hash, 'user_id' => $user->id, 'reason' => $input['reason'], 'before' => $p['before'], 'after' => [], 'effects' => []]);
            // Move positions aside first; the unique order/position key also applies to historical retired items.
            $temporary = ($order->items()->max('position') ?? 0) + 100;
            foreach ($order->items()->get() as $row) {
                $row->position = $temporary++;
                $row->save();
            }
            foreach ($p['active'] as $itemId => $item) {
                $row = $order->items()->find($itemId);
                if (! $row) {
                    $row = new Item;
                    $row->id = $itemId;
                    $row->order_id = $id;
                }$row->fill([...$item, 'active' => true, 'commercial' => $p['commercial']['articles'][$item['position']]]);
                $row->save();
            }
            $retiredPosition = count($p['active']);
            foreach ($order->items()->get() as $row) {
                if (! isset($p['active'][$row->id])) {
                    $row->position = $retiredPosition++;
                    if ($row->active) {
                        $row->commercial = [...$row->commercial, 'currency' => $order->commercial['currency'], 'exchange_rate' => $order->commercial['exchange_rate']];
                    }
                    $row->active = false;
                    $row->quantity = 0;
                    $row->save();
                }
            }
            foreach ($p['restore'] as $line) {
                Surplus::create(['revision_id' => $revision->id, 'item_id' => $line['item_id'], 'quantity' => -$line['quantity'], 'source_event_id' => $line['source_event_id'], 'disposition' => 'hold']);
            }
            foreach ($p['excess'] as $itemId => $qty) {
                if ($qty > 0) {
                    Surplus::create(['revision_id' => $revision->id, 'item_id' => $itemId, 'quantity' => $qty, 'disposition' => $input['excess'][$itemId]]);
                }
            }
            $order->unsetRelation('items');
            $positiveReturns = array_values(array_filter($returns, fn ($l) => $l['quantity'] > 0));
            if ($positiveReturns) {
                $this->materials->mutate($user, $id, $order->version, (string) Str::uuid(), 'return', $positiveReturns);
                $order->refresh();
            }
            $deficits = [];
            foreach ($p['needs'] as $n) {
                $remaining = 0;
                foreach (Allocation::where('item_id', $n['item_id'])->where('material_index', $n['material_index'])->get() as $a) {
                    $remaining += $this->materials->balance($a)['remaining'];
                }
                $delta = Math::number(Math::of($n['needed'])->minus(Math::of($remaining)), 6);
                if ($delta > 0) {
                    $deficits[$n['item_id'].':'.$n['material_index']] = [...$n, 'delta' => $delta];
                }
            }
            $selections = $input['selections'] ?? [];
            if (! $selections) {
                $selections = array_map(fn ($n) => ['item_id' => $n['item_id'], 'material_index' => $n['material_index'], 'target' => $n['target'], 'sources' => [['inventory_detail_id' => $n['origin'], 'quantity' => $n['delta']]]], array_values($deficits));
            }
            if (count($selections) !== count($deficits)) {
                $this->fail('Selecciona solo el déficit de cada material pendiente.');
            }
            foreach ($selections as &$s) {
                $key = $s['item_id'].':'.$s['material_index'];
                if (! isset($deficits[$key])) {
                    $this->fail('Selección adicional de material no requerido.');
                }$s['target'] = $deficits[$key]['target'];
            }unset($s);
            if ($selections) {
                $this->materials->mutate($user, $id, $order->version, (string) Str::uuid(), 'additional', $selections);
                $order->refresh();
            }
            $public = $p['commercial'];
            $public['order_revision_number'] = $revision->number;
            foreach ($order->items()->where('active', true)->get() as $row) {
                $row->commercial = [...$public['articles'][$row->position], 'currency' => $public['currency'], 'exchange_rate' => $public['exchange_rate']];
                $row->save();
            }
            $order->commercial = $public;
            $order->snapshot = $p['snapshot'];
            $order->delivery_local = $p['delivery_local'];
            $order->delivery_at = $p['delivery_at'];
            $order->version++;
            $order->save();
            $order->unsetRelation('items');
            $revision->after = $this->state($order);
            $revision->effects = ['returns' => $returns, 'additional' => $selections, 'surplus' => $p['excess'], 'restore' => $p['restore']];
            $revision->save();
            if (app()->bound(CustomJobOrderRevisionFinancial::class)) {
                app(CustomJobOrderRevisionFinancial::class)->apply($user, $order, $revision);
            }

            return $order;
        });
    }
}
