<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class CustomJobCalculationContext
{
    public function storeIds(User $user): ?array
    {
        if ($user->id === TenantManager::getTenant()->owner_id || $user->roles()->where('organization_id', TenantManager::getTenantId())
            ->whereHas('permissions', fn ($q) => $q->where('name', 'store.index'))->exists()) {
            return null;
        }

        return $user->stores()->wherePivot('status', 'active')->pluck('stores.id')->all();
    }

    public function store(User $user, string $id): Store
    {
        $store = Store::where('status', 'active')->findOrFail($id);
        $allowed = $this->storeIds($user);
        abort_if($allowed !== null && ! in_array($id, $allowed, true), 403, 'No tienes acceso a esta sucursal.');

        return $store;
    }

    public function inventories(User $user): Builder
    {
        $query = Inventory::where('status', 'active');
        $allowed = $this->storeIds($user);
        if ($allowed !== null) {
            $query->where(fn ($q) => $q->whereIn('store_id', $allowed)->orWhereHas('stores', fn ($s) => $s->whereIn('stores.id', $allowed)));
        }

        return $query;
    }

    public function commercial(Store $store, string $currency): array
    {
        $setting = fn ($key) => Setting::where('key', $key)->where('type', 'global')->value('value');
        $acceptedRaw = $setting('accepted_currencies');
        $accepted = $acceptedRaw ? json_decode($acceptedRaw, true) : ['NIO', 'USD'];
        if (! is_array($accepted)) {
            $accepted = array_map('trim', explode(',', $acceptedRaw));
        }
        if (! in_array($currency, $accepted, true)) {
            throw ValidationException::withMessages(['currency' => 'Esta moneda está desactivada en Facturación.']);
        }
        $rate = $setting('usd_exchange_rate') ?: '36.5';
        if (! is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 1000000) {
            throw ValidationException::withMessages(['currency' => 'Revisa la tasa de cambio de Facturación.']);
        }

        return ['base_currency' => 'NIO', 'currency' => $currency, 'usd_exchange_rate' => (float) $rate, 'accepted_currencies' => $accepted,
            'store_id' => $store->id, 'store_symbol' => $store->store_currency];
    }
}
