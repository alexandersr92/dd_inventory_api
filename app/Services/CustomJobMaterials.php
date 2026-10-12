<?php

namespace App\Services;

use App\Contracts\CustomJobReservation;
use App\Models\CustomJobMaterialAllocation as Allocation;
use App\Models\CustomJobMaterialEvent as Event;
use App\Models\CustomJobMaterialOperation as Operation;
use App\Models\CustomJobOrder;
use App\Models\InventoryDetail;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\CustomJobMath as Math;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomJobMaterials implements CustomJobReservation
{
    public function __construct(private CustomJobCalculationContext $context, private InventoryMovementService $movements) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['materials' => $message]);
    }

    private function qty(mixed $value, bool $zero = false): string
    {
        try {
            $n = Math::of($value);
            $v = (string) $n->toScale(6, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            $this->fail('La cantidad admite hasta seis decimales.');
        }
        if ($n->isNegative() || (! $zero && $n->isZero()) || $n->isGreaterThanOrEqualTo(Math::of('100000000000000'))) {
            $this->fail('Cantidad fuera del rango permitido.');
        }

        return $v;
    }

    private function sum($events, string $kind, string $field = 'quantity'): float
    {
        $n = Math::of(0);
        foreach ($events->where('kind', $kind) as $e) {
            $n = $n->plus(Math::of($e->getRawOriginal($field)));
        }

        return Math::number($n, 6);
    }

    public function balance(Allocation $a): array
    {
        $events = Event::where('allocation_id', $a->id)->get();
        $assigned = $this->sum($events, 'assign');
        $used = $this->sum($events, 'consume');
        $returned = $this->sum($events, 'return');
        $remaining = Math::number(Math::of($assigned)->minus(Math::of($used))->minus(Math::of($returned)), 6);
        $physical = Math::number(Math::of($this->sum($events, 'assign', 'physical_quantity'))->minus(Math::of($this->sum($events, 'consume', 'physical_quantity')))->minus(Math::of($this->sum($events, 'return', 'physical_quantity'))), 6);

        return ['assigned' => $assigned, 'used' => $used, 'returned' => $returned, 'remaining' => $remaining, 'physical_remaining' => $physical,
            'shortage_remaining' => Math::number(Math::of($remaining)->minus(Math::of($physical)), 6), 'recovered' => $this->sum($events, 'recover')];
    }

    public function requirements(CustomJobOrder $order): array
    {
        $rows = [];
        foreach ($order->items as $item) {
            if ($item->active === false) {
                continue;
            }
            foreach ($item->composition['materials'] as $index => $m) {
                $rows[] = ['item_id' => $item->id, 'item_name' => $item->name, 'material_index' => $m['slot'] ?? $index, 'product_id' => $m['product_id'], 'name' => $m['name'],
                    'base_unit' => $m['base_unit'], 'required' => $m['base_quantity'], 'inventory_detail_id' => $m['inventory_detail_id'], 'unit_cost_nio' => $m['base_unit_cost_nio']];
            }
        }

        return $rows;
    }

    private function source(User $user, string $id, array $requirement, bool $lock = false): InventoryDetail
    {
        $detail = InventoryDetail::whereIn('inventory_id', $this->context->inventories($user)->pluck('id'))->where('status', 'active')->with(['product', 'inventory'])
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        $p = $detail->product;
        if (! $p || $p->id !== $requirement['product_id'] || $p->status !== 'active' || ! in_array($p->product_usage, ['supply', 'both'], true)
            || MaterialUnits::canonical($p->unit_of_measure) !== $requirement['base_unit']) {
            $this->fail('El origen debe ser el mismo producto activo Insumo/Ambos y conservar su unidad base.');
        }

        return $detail;
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

    private function operation(User $user, CustomJobOrder $order, string $key, string $kind, array $payload): ?Operation
    {
        $hash = $this->hash([$kind, $payload]);
        $existing = Operation::where('order_id', $order->id)->where('request_id', $key)->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La solicitud ya se utilizó con otra selección.');

            return null;
        }

        return Operation::create(['order_id' => $order->id, 'request_id' => $key, 'kind' => $kind, 'request_hash' => $hash, 'user_id' => $user->id]);
    }

    public function reserve(User $user, CustomJobOrder $order, array $selection = []): void
    {
        $this->assign($user, $order, $order->id, $selection, true);
    }

    private function assign(User $user, CustomJobOrder $order, string $key, array $selection, bool $initial): void
    {
        $requirements = $this->requirements($order);
        $planned = [];
        if (! $selection && $initial) {
            $selection = array_map(fn ($r) => ['item_id' => $r['item_id'], 'material_index' => $r['material_index'], 'target' => $r['required'],
                'sources' => [['inventory_detail_id' => $r['inventory_detail_id'], 'quantity' => $r['required']]]], $requirements);
        }
        if ($initial && count($selection) !== count($requirements)) {
            $this->fail('Selecciona todos los materiales del pedido.');
        }
        $seen = [];
        foreach ($selection as $line) {
            $r = collect($requirements)->first(fn ($r) => $r['item_id'] === $line['item_id'] && $r['material_index'] === $line['material_index']);
            if (! $r) {
                $this->fail('El material no pertenece al artículo del pedido.');
            }
            $index = $line['item_id'].':'.$line['material_index'];
            if (isset($seen[$index])) {
                $this->fail('Material repetido.');
            }$seen[$index] = true;
            $target = $initial ? $r['required'] : $this->qty($line['target']);
            $already = Math::of(0);
            if (! $initial) {
                foreach (Allocation::where('item_id', $r['item_id'])->where('material_index', $r['material_index'])->get() as $a) {
                    $b = $this->balance($a);
                    $already = $already->plus(Math::of($b['used']))->plus(Math::of($b['remaining']));
                }
            }
            $delta = Math::of($target)->minus($already);
            if ($delta->isNegative()) {
                $this->fail('Para reducir el apartado utiliza retornos selectivos.');
            }
            $total = Math::of(0);
            foreach ($line['sources'] as $origin) {
                $qty = $this->qty($origin['quantity']);
                $total = $total->plus(Math::of($qty));
                $planned[] = ['requirement' => $r, 'detail_id' => $origin['inventory_detail_id'], 'quantity' => $qty];
            }
            if (! $total->isEqualTo($delta)) {
                $this->fail('La suma de orígenes debe coincidir con lo pendiente de asignar (objetivo menos usado y restante).');
            }
        }
        $op = $this->operation($user, $order, $key, $initial ? 'initial' : 'additional', $selection);
        if (! $op) {
            return;
        }
        // Product then stock locks, globally sorted; no inverse order between different source inventories.
        $productIds = array_unique(array_column(array_column($planned, 'requirement'), 'product_id'));
        sort($productIds);
        Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
        $ids = array_unique(array_column($planned, 'detail_id'));
        sort($ids);
        $details = [];
        foreach ($ids as $id) {
            $r = collect($planned)->firstWhere('detail_id', $id)['requirement'];
            $details[$id] = $this->source($user, $id, $r, true);
        }
        foreach ($planned as $row) {
            $r = $row['requirement'];
            $d = $details[$row['detail_id']];
            // Revalidate each row, including when a malicious selection reused an origin for a different product.
            if ($d->product_id !== $r['product_id']) {
                $this->fail('Origen de producto incorrecto.');
            }
            $before = Math::of($d->getRawOriginal('quantity'));
            $qty = Math::of($row['quantity']);
            $physical = $before->isNegative() ? Math::of(0) : ($before->isLessThan($qty) ? $before : $qty);
            if ($before->minus($qty)->isNegative() && ! $this->movements->allowsNegativeStock($d->inventory->store_id, TenantManager::getTenantId())) {
                $this->fail("Stock insuficiente para {$r['name']}. Revisa los orígenes seleccionados.");
            }
            $a = Allocation::firstOrCreate(['item_id' => $r['item_id'], 'material_index' => $r['material_index'], 'inventory_detail_id' => $d->id],
                ['order_id' => $order->id, 'product_id' => $r['product_id'], 'name' => $r['name'], 'inventory_name' => $d->inventory->name, 'base_unit' => $r['base_unit'], 'unit_cost_nio' => $r['unit_cost_nio']]);
            $event = Event::create(['operation_id' => $op->id, 'allocation_id' => $a->id, 'kind' => 'assign', 'quantity' => $row['quantity'], 'physical_quantity' => $this->qty(Math::number($physical, 6), true)]);
            $movement = $this->movement($user, $d->id, $row['quantity'], 'custom_job_reserve', $event);
            $event->movement_id = $movement->id;
            $event->save();
            $d->refresh();
        }
    }

    private function movement(User $user, string $id, string $quantity, string $type, Event $event)
    {
        return $this->movements->recordMovement(['inventory_detail_id' => $id, 'quantity' => $quantity, 'type' => $type, 'user_id' => $user->id, 'reference_type' => 'CustomJobMaterialEvent', 'reference_id' => $event->id, 'reason' => 'Material de trabajo personalizado']);
    }

    public function mutate(User $user, string $orderId, int $version, string $requestId, string $kind, array $lines): void
    {
        DB::transaction(function () use ($user, $orderId, $version, $requestId, $kind, $lines) {
            $orders = app(CustomJobOrders::class);
            $read = $orders->accessible($user, $orderId);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $orders->accessible($user, $orderId, true);
            $existing = Operation::where('order_id', $orderId)->where('request_id', $requestId)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $this->hash([$kind, $lines])), 409, 'La solicitud ya se utilizó con otra selección.');

                return;
            }
            abort_unless($order->status === 'confirmed' && $order->version === $version, 409, 'El pedido debe estar confirmado y conservar su versión vigente.');
            if ($kind === 'additional') {
                $this->assign($user, $order, $requestId, $lines, false);
            } else {
                $this->events($user, $order, $requestId, $kind, $lines);
            }
            $order->version++;
            $order->save();
        });
    }

    /** Plan 09 calls this inside its production transaction. No stock movement on consumption. */
    private function events(User $user, CustomJobOrder $order, string $key, string $kind, array $lines): void
    {
        $op = $this->operation($user, $order, $key, $kind, $lines);
        if (! $op) {
            return;
        }
        $ids = array_unique(array_column($lines, 'allocation_id'));
        sort($ids);
        $allocations = Allocation::where('order_id', $order->id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        // Destination stock locks are sorted before any returns, like allocation exits.
        $destinations = [];
        if (in_array($kind, ['return', 'recover'], true)) {
            foreach ($lines as $line) {
                $a = $allocations->get($line['allocation_id']);
                abort_unless($a, 404);
                $destination = $line['inventory_detail_id'] ?? $a->inventory_detail_id;
                if ($destination !== $a->inventory_detail_id) {
                    $allowed = $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'inventory.update'))->exists();
                    abort_unless($allowed, 403, 'Cambiar destino requiere permiso de editar inventarios.');
                }
                $destinations[$destination] = ['product_id' => $a->product_id, 'base_unit' => $a->base_unit];
            }
        }
        ksort($destinations);
        $productIds = array_unique(array_column($destinations, 'product_id'));
        sort($productIds);
        Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
        $stocks = [];
        foreach ($destinations as $id => $r) {
            $stocks[$id] = $this->source($user, $id, $r, true);
        }
        foreach ($lines as $line) {
            $a = $allocations->get($line['allocation_id']);
            abort_unless($a, 404);
            $q = $this->qty($line['quantity'], true);
            if (Math::of($q)->isZero()) {
                continue;
            }
            $b = $this->balance($a);
            $source = null;
            if ($kind === 'consume') {
                if (Math::of($q)->isGreaterThan(Math::of($b['physical_remaining']))) {
                    $this->fail('El uso excede el apartado físicamente disponible. Requiere asignación adicional.');
                }$physical = $q;
            } elseif ($kind === 'return') {
                if (Math::of($q)->isGreaterThan(Math::of($b['remaining']))) {
                    $this->fail('El retorno excede el material apartado sin usar.');
                }
                if (($line['inventory_detail_id'] ?? $a->inventory_detail_id) !== $a->inventory_detail_id
                    && Math::of($q)->isGreaterThan(Math::of($b['physical_remaining']))) {
                    $this->fail('Un faltante solo puede liberarse en su inventario de origen; no acredita material físico en otro destino.');
                }
                $physical = $this->qty(min((float) $q, $b['physical_remaining']), true);
            } elseif ($kind === 'recover') {
                $source = Event::where('allocation_id', $a->id)->where('kind', 'consume')->findOrFail($line['source_event_id']);
                $recovered = $this->sum(Event::where('source_event_id', $source->id)->get(), 'recover');
                if (Math::of($q)->isGreaterThan(Math::of($source->getRawOriginal('quantity'))->minus(Math::of($recovered)))) {
                    $this->fail('La recuperación excede el consumo recuperable neto.');
                }$physical = $q;
            } else {
                $this->fail('Operación de material inválida.');
            }
            $event = Event::create(['operation_id' => $op->id, 'allocation_id' => $a->id, 'kind' => $kind, 'quantity' => $q, 'physical_quantity' => $physical, 'source_event_id' => $source?->id]);
            if ($kind !== 'consume') {
                $destination = $line['inventory_detail_id'] ?? $a->inventory_detail_id;
                if ($stocks[$destination]->product_id !== $a->product_id
                    || MaterialUnits::canonical($stocks[$destination]->product->unit_of_measure) !== $a->base_unit) {
                    $this->fail('El destino debe conservar el producto y la unidad base del material.');
                }
                $movement = $this->movement($user, $destination, $q, 'custom_job_return', $event);
                $event->movement_id = $movement->id;
                $event->save();
            }
        }
    }

    public function view(User $user, CustomJobOrder $order): array
    {
        $costs = $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
        $allocations = Allocation::where('order_id', $order->id)->get();
        $held = Math::of(0);
        $rows = $allocations->map(function ($a) use ($costs, &$held) {
            $b = $this->balance($a);
            $value = Math::number(Math::of($b['physical_remaining'])->multipliedBy(Math::of($a->getRawOriginal('unit_cost_nio'))));
            $held = $held->plus(Math::of($value));

            return [...$a->only(['id', 'item_id', 'material_index', 'inventory_detail_id', 'product_id', 'name', 'inventory_name', 'base_unit']), ...$b, ...($costs ? ['unit_cost_nio' => (float) $a->unit_cost_nio, 'held_value_nio' => $value] : []),
                'events' => Event::where('allocation_id', $a->id)->get()->map(fn ($e) => [...$e->only(['id', 'kind', 'source_event_id', 'movement_id', 'created_at']), 'quantity' => (float) $e->quantity, 'physical_quantity' => (float) $e->physical_quantity])->all()];
        });
        $requirements = $this->requirements($order);
        $origins = [];
        foreach ($requirements as &$r) {
            if (! $costs) {
                unset($r['unit_cost_nio']);
            }
            foreach (InventoryDetail::whereIn('inventory_id', $this->context->inventories($user)->pluck('id'))->where('product_id', $r['product_id'])->where('status', 'active')->with(['inventory', 'product'])->get() as $d) {
                if ($d->product->status !== 'active' || ! in_array($d->product->product_usage, ['supply', 'both'], true) || MaterialUnits::canonical($d->product->unit_of_measure) !== $r['base_unit']) {
                    continue;
                }
                $origins[$d->id] = ['id' => $d->id, 'product_id' => $d->product_id, 'inventory_name' => $d->inventory->name, 'available' => (float) $d->quantity, 'allow_negative' => $this->movements->allowsNegativeStock($d->inventory->store_id, TenantManager::getTenantId()), ...($costs ? ['free_value_nio' => Math::number(Math::of(max(0, (float) $d->quantity))->multipliedBy(Math::of($d->product->getRawOriginal('cost'))))] : [])];
            }
        }

        return ['requirements' => $requirements, 'origins' => array_values($origins), 'allocations' => $rows->all(), ...($costs ? ['held_value_nio' => Math::number($held)] : [])];
    }
}
