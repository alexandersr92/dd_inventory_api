<?php

namespace App\Services;

use App\Models\CustomJobMaterialAllocation as Allocation;
use App\Models\CustomJobMaterialEvent as MaterialEvent;
use App\Models\CustomJobMaterialOperation as Operation;
use App\Models\CustomJobOrder;
use App\Models\CustomJobProductionEvent as Progress;
use App\Models\CustomJobWorkOrder as Work;
use App\Models\Store;
use App\Models\User;
use App\Support\CustomJobMath as Math;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomJobProduction
{
    public function __construct(private CustomJobOrders $orders, private CustomJobMaterials $materials) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['production' => $message]);
    }

    private function lock(User $user, string $id): CustomJobOrder
    {
        $read = $this->orders->accessible($user, $id);
        Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();

        return $this->orders->accessible($user, $id, true);
    }

    public function generate(User $user, string $id, int $version): Work
    {
        return DB::transaction(function () use ($user, $id, $version) {
            $order = $this->lock($user, $id);
            abort_unless($order->status === 'confirmed', 409, 'Solo se produce un pedido confirmado y vigente.');
            $existing = Work::where('order_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($order->version === $version, 409, 'El pedido cambió. Recarga antes de generar la orden.');
            $work = Work::create(['order_id' => $id, 'user_id' => $user->id]);
            $order->version++;
            $order->save();

            return $work;
        });
    }

    private function quantity(mixed $value): string
    {
        try {
            $n = Math::of($value);
            $q = (string) $n->toScale(0, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            $this->fail('Las piezas deben ser cantidades enteras.');
        }
        if ($n->isNegative() || $n->isGreaterThanOrEqualTo(Math::of('1000001'))) {
            $this->fail('Cantidad de piezas fuera de rango.');
        }

        return $q;
    }

    private function hash(array $input): string
    {
        unset($input['version']);
        $sort = function (array $a) use (&$sort) {
            if (! array_is_list($a)) {
                ksort($a);
            } foreach ($a as &$v) {
                if (is_array($v)) {
                    $v = $sort($v);
                }
            }

            return $a;
        };

        return hash('sha256', json_encode($sort($input), JSON_THROW_ON_ERROR));
    }

    public function record(User $user, string $id, array $input): void
    {
        DB::transaction(function () use ($user, $id, $input) {
            $order = $this->lock($user, $id);
            $work = Work::where('order_id', $id)->lockForUpdate()->firstOrFail();
            $hash = $this->hash($input);
            $old = Progress::where('work_order_id', $work->id)->where('request_id', $input['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'La solicitud ya tiene otro progreso registrado.');

                return;
            }
            abort_unless($order->status === 'confirmed' && $order->version === $input['version'], 409, 'El pedido debe estar confirmado y conservar su versión vigente.');
            $item = $order->items()->findOrFail($input['item_id']);
            $at = $this->orders->localInstant($input['occurred_local'], $order->delivery_timezone);
            abort_if($at > now('UTC')->format('Y-m-d H:i:s'), 422, 'El progreso no puede tener una fecha futura.');
            $source = null;
            $kind = 'progress';
            $consumption = $input['consumption'];
            if (! empty($input['corrects_event_id'])) {
                $kind = 'correction';
                $source = Progress::where('work_order_id', $work->id)->where('item_id', $item->id)->where('kind', 'progress')->findOrFail($input['corrects_event_id']);
                abort_if(Progress::where('corrects_event_id', $source->id)->exists(), 409, 'Ese avance ya fue corregido.');
                if ($consumption || trim($input['note']) === '' || (float) $input['produced'] !== 0.0 || (float) $input['discarded'] !== 0.0) {
                    $this->fail('La corrección requiere motivo y conserva el consumo original, sin material nuevo.');
                }
                $produced = Math::of($source->getRawOriginal('produced'))->negated();
                $discarded = Math::of($source->getRawOriginal('discarded'))->negated();
            } else {
                $produced = Math::of($this->quantity($input['produced']));
                $discarded = Math::of($this->quantity($input['discarded']));
                if ($produced->isZero() && $discarded->isZero() && ! $consumption) {
                    $this->fail('Registra piezas o material preparado.');
                }
            }
            $net = Math::of(0);
            foreach (Progress::where('work_order_id', $work->id)->where('item_id', $item->id)->get() as $event) {
                $net = $net->plus(Math::of($event->getRawOriginal('produced')));
            }
            $next = $net->plus($produced);
            if ($next->isNegative() || $next->isGreaterThan(Math::of($item->getRawOriginal('quantity')))) {
                $this->fail('El avance excede las piezas vigentes del pedido. Requiere revisión antes de producir más.');
            }
            foreach ($consumption as $line) {
                Allocation::where('order_id', $id)->where('item_id', $item->id)->findOrFail($line['allocation_id']);
            }
            // Consume the existing assignment in the same transaction; never decrement warehouse stock again.
            $this->materials->mutate($user, $id, $input['version'], $input['request_id'], 'consume', $consumption);
            $operation = Operation::where('order_id', $id)->where('request_id', $input['request_id'])->firstOrFail();
            Progress::create(['work_order_id' => $work->id, 'item_id' => $item->id, 'request_id' => $input['request_id'], 'request_hash' => $hash,
                'kind' => $kind, 'produced' => (string) $produced->toScale(6), 'discarded' => (string) $discarded->toScale(6),
                'occurred_local' => $input['occurred_local'], 'timezone' => $order->delivery_timezone, 'occurred_at' => $at, 'user_id' => $user->id,
                'note' => $input['note'], 'corrects_event_id' => $source?->id, 'material_operation_id' => $operation->id]);
        });
    }

    public function view(User $user, CustomJobOrder $order): ?array
    {
        $work = Work::where('order_id', $order->id)->first();
        if (! $work) {
            return null;
        }
        $events = Progress::where('work_order_id', $work->id)->orderBy('created_at')->orderBy('id')->get();
        $costs = $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
        $actual = Math::of(0);
        $items = [];
        $complete = true;
        foreach ($order->items as $item) {
            $produced = Math::of(0);
            $discarded = Math::of(0);
            foreach ($events->where('item_id', $item->id) as $e) {
                $produced = $produced->plus(Math::of($e->getRawOriginal('produced')));
                $discarded = $discarded->plus(Math::of($e->getRawOriginal('discarded')));
            }
            $pending = Math::of($item->getRawOriginal('quantity'))->minus($produced);
            if (! $pending->isZero()) {
                $complete = false;
            }
            $requirements = array_values(array_filter($this->materials->requirements($order), fn ($r) => $r['item_id'] === $item->id));
            foreach ($requirements as &$r) {
                $r['per_piece'] = Math::number(Math::of($r['required'])->dividedBy(Math::of($item->getRawOriginal('quantity'))), 6);
                unset($r['unit_cost_nio']);
            } unset($r);
            foreach (Allocation::where('order_id', $order->id)->where('item_id', $item->id)->get() as $a) {
                $actual = $actual->plus(Math::of($this->materials->balance($a)['used'])->multipliedBy(Math::of($a->getRawOriginal('unit_cost_nio'))));
            }
            $items[] = ['id' => $item->id, 'name' => $item->name, 'ordered' => (float) $item->quantity, 'produced' => Math::number($produced, 6), 'discarded' => Math::number($discarded, 6), 'pending' => Math::number($pending, 6), 'materials' => $requirements];
        }

        return [...$work->only(['id', 'order_id', 'user_id', 'created_at']), 'status' => $complete ? 'ready' : ($events->isEmpty() ? 'pending' : 'in_progress'), 'items' => $items,
            'events' => $events->map(fn ($e) => [...$e->only(['id', 'item_id', 'kind', 'occurred_local', 'timezone', 'user_id', 'note', 'corrects_event_id', 'created_at']),
                'occurred_at' => str_replace(' ', 'T', $e->occurred_at).'Z', 'produced' => (float) $e->produced, 'discarded' => (float) $e->discarded,
                'consumption' => MaterialEvent::where('operation_id', $e->material_operation_id)->where('kind', 'consume')->get()->map(fn ($m) => ['id' => $m->id, 'allocation_id' => $m->allocation_id, 'quantity' => (float) $m->quantity])->all()])->all(),
            ...($costs ? ['actual_material_cost_nio' => Math::number($actual), 'budget' => ['currency' => $order->commercial['currency'], 'totals' => $order->snapshot['totals']]] : [])];
    }
}
