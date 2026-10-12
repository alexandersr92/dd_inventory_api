<?php

namespace App\Services;

use App\Contracts\CustomJobReservation;
use App\Models\CustomJobOrder;
use App\Models\Store;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomJobOrders
{
    public function __construct(private CustomJobCalculationContext $context, private CustomJobQuotes $quotes) {}

    public function accessible(User $user, string $id, bool $lock = false): CustomJobOrder
    {
        $order = CustomJobOrder::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        $this->context->store($user, $order->store_id);

        return $order;
    }

    private function delivery(string $local, string $zone): string
    {
        if (! in_array($zone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['delivery_local' => 'La zona horaria de la sucursal no es válida.']);
        }
        $timezone = new DateTimeZone($zone);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $timezone);
        if (! $date || $date->format('Y-m-d\TH:i') !== $local) {
            throw ValidationException::withMessages(['delivery_local' => 'Fecha/hora inválida o inexistente en la zona de la sucursal.']);
        }
        // Ambiguous DST times need an explicit choice, not an arbitrary offset.
        foreach ($timezone->getTransitions($date->getTimestamp() - 86400, $date->getTimestamp() + 86400) ?: [] as $transition) {
            $alternative = $date->getTimestamp() + $date->getOffset() - $transition['offset'];
            if ($alternative !== $date->getTimestamp() && $date->setTimestamp($alternative)->format('Y-m-d\TH:i') === $local) {
                throw ValidationException::withMessages(['delivery_local' => 'Esta hora se repite por cambio horario. Elige otra hora.']);
            }
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ((int) $utc->format('Y') < 1000 || (int) $utc->format('Y') > 9999) {
            throw ValidationException::withMessages(['delivery_local' => 'La fecha queda fuera del rango permitido.']);
        }

        return $utc->format('Y-m-d H:i:s');
    }

    public function localInstant(string $local, string $zone): string
    {
        return $this->delivery($local, $zone);
    }

    public function convert(User $user, string $quoteId, array $data): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $quoteId, $data) {
            $read = $this->quotes->accessible($user, $quoteId);
            $store = Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $quote = $this->quotes->accessible($user, $quoteId, true);
            $existing = CustomJobOrder::where('quote_id', $quote->id)->first();
            if ($existing) {
                abort_unless($existing->quote_revision_id === $data['revision_id'] && $existing->delivery_local === $data['delivery_local'], 409, 'El pedido ya existe con otra revisión o compromiso. Abre el pedido vigente.');

                return $existing;
            }
            abort_unless($quote->version === $data['version'], 409, 'La cotización cambió. Recarga antes de convertir.');
            $revision = $quote->revisions()->firstOrFail();
            abort_unless($quote->status === 'issued' && $quote->issued_revision_id === $data['revision_id'] && $revision->id === $data['revision_id'], 409, 'Solo se puede convertir la revisión vigente emitida.');
            $zone = $store->custom_job_timezone;
            $utc = $this->delivery($data['delivery_local'], $zone);
            $store->custom_job_order_number++;
            $store->save();
            $public = $revision->commercial;
            $order = CustomJobOrder::create(['store_id' => $store->id, 'quote_id' => $quote->id, 'quote_revision_id' => $revision->id,
                'client_id' => $public['client']['id'], 'client_name' => $public['client']['name'], 'user_id' => $user->id,
                'number' => 'PED-'.str_pad($store->custom_job_order_number, 6, '0', STR_PAD_LEFT), 'status' => 'draft', 'version' => 1,
                'delivery_local' => $data['delivery_local'], 'delivery_timezone' => $zone, 'delivery_at' => $utc,
                'commercial' => $public, 'snapshot' => $revision->snapshot]);
            foreach ($public['articles'] as $position => $article) {
                $order->items()->create(['position' => $position, 'name' => $article['name'], 'quantity' => $article['quantity'],
                    'commercial' => $article, 'composition' => $revision->snapshot['articles'][$position]]);
            }
            $quote->status = 'converted';
            $quote->version++;
            $quote->save();

            return $order;
        });
    }

    public function commitment(User $user, string $id, array $data): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $read = $this->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->accessible($user, $id, true);
            abort_unless($order->version === $data['version'], 409, 'El pedido cambió. Recarga antes de continuar.');
            abort_unless($order->status === 'draft', 409, 'El compromiso operativo se modifica mediante el flujo de edición de pedidos.');
            $order->delivery_at = $this->delivery($data['delivery_local'], $order->delivery_timezone);
            $order->delivery_local = $data['delivery_local'];
            $order->version++;
            $order->save();

            return $order;
        });
    }

    public function confirm(User $user, string $id, int $version, array $selection = []): CustomJobOrder
    {
        return DB::transaction(function () use ($user, $id, $version, $selection) {
            $read = $this->accessible($user, $id);
            Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $order = $this->accessible($user, $id, true);
            if ($order->status === 'confirmed') {
                return $order;
            }
            abort_unless($order->version === $version && $order->status === 'draft', 409, 'El pedido cambió. Recarga antes de confirmar.');
            abort_unless(app()->bound(CustomJobReservation::class), 409, 'Confirmación deshabilitada: falta integrar el apartado de materiales del plan 08.');
            $this->delivery($order->delivery_local, $order->delivery_timezone);
            app(CustomJobReservation::class)->reserve($user, $order, $selection);
            $order->status = 'confirmed';
            $order->confirmed_at = now('UTC')->format('Y-m-d H:i:s');
            $order->version++;
            $order->save();

            return $order;
        });
    }

    public function representation(User $user, CustomJobOrder $order): array
    {
        $costs = $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();

        return [...$order->only(['id', 'store_id', 'quote_id', 'quote_revision_id', 'client_id', 'client_name', 'user_id', 'number', 'status', 'version', 'delivery_local', 'delivery_timezone', 'confirmed_at', 'created_at', 'updated_at']),
            'delivery_at' => str_replace(' ', 'T', $order->delivery_at).'Z', 'commercial' => $order->commercial,
            'items' => $order->items->map(fn ($i) => [...$i->only(['id', 'position', 'name', 'quantity', 'commercial']), ...($costs ? ['composition' => $i->composition] : [])])->all(),
            ...($costs ? ['snapshot' => $order->snapshot] : []),
            'confirmation' => ['available' => $order->status === 'draft' && app()->bound(CustomJobReservation::class), 'reason' => app()->bound(CustomJobReservation::class) ? null : 'Falta integrar el apartado de materiales del plan 08.'],
            // Production provides a work-order link; other owning modules still return null.
            'operations' => ['production' => ($work = \App\Models\CustomJobWorkOrder::where('order_id', $order->id)->first()) ? ['id' => $work->id] : null, 'delivery' => app(CustomJobDeliveries::class)->view($order), 'payment' => null, 'billing' => null]];
    }
}
