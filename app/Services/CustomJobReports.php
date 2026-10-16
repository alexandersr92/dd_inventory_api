<?php

namespace App\Services;

use App\Models\CustomJobMaterialAllocation;
use App\Models\CustomJobOrder;
use App\Models\InventoryDetail;
use App\Models\Invoice;
use App\Models\User;
use App\Support\CustomJobMath as Math;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CustomJobReports
{
    public function __construct(
        private CustomJobCalculationContext $context,
        private CustomJobDeliveries $deliveries,
        private CustomJobMaterials $materials,
        private CustomJobPayments $payments,
        private CustomJobProduction $production
    ) {}

    public function index(User $user, array $filters): array
    {
        $store = $this->context->store($user, $filters['store_id']);
        $costs = $this->canSeeCosts($user);
        $query = CustomJobOrder::where('store_id', $store->id)
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('number', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%')))
            ->when($filters['delivery_from'] ?? null, fn ($q, $date) => $q->where('delivery_local', '>=', $date.'T00:00'))
            ->when($filters['delivery_to'] ?? null, fn ($q, $date) => $q->where('delivery_local', '<=', $date.'T23:59'))
            ->with('items')
            ->orderBy('delivery_at')
            ->orderBy('id');

        $rows = $query->get()->map(fn (CustomJobOrder $order) => $this->row($user, $order, $costs));
        $rows = $this->dueFilter($rows, $filters['due_state'] ?? 'all');
        $summary = $this->summary($rows, $costs);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 25;
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'data' => $rows->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'timezone' => $store->custom_job_timezone,
            'costs_visible' => $costs,
            'summary' => $summary,
        ];
    }

    private function row(User $user, CustomJobOrder $order, bool $costs): array
    {
        $delivery = $this->deliveries->view($order);
        $production = $this->production->view($user, $order);
        $items = collect($delivery['items'])->map(function (array $item) use ($production) {
            $produced = collect($production['items'] ?? [])->firstWhere('id', $item['id']);

            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'active' => $item['active'],
                'ordered' => $item['ordered'],
                'produced' => (float) ($produced['produced'] ?? 0),
                'discarded' => (float) ($produced['discarded'] ?? 0),
                'delivered_net' => $item['delivered_net'],
                'pending_production' => max(0, (float) $item['ordered'] - (float) ($produced['produced'] ?? 0)),
                'pending_delivery' => max(0, (float) $item['pending']),
            ];
        })->values();
        $pendingDelivery = $items->sum('pending_delivery');
        $pendingProduction = $items->sum('pending_production');
        $overdue = $order->status !== 'cancelled' && $pendingDelivery > 0 && CarbonImmutable::parse($order->delivery_at, 'UTC')->isPast();
        $material = $this->materials($order);
        $payments = $this->payments->summary($order);
        $invoice = Invoice::where('custom_job_order_id', $order->id)
            ->whereNotIn('invoice_status', ['proforma', 'canceled', 'cancelled'])
            ->with('invoiceDetails')
            ->first();
        $commercial = $order->commercial;
        $revenueNio = $this->nio($commercial['totals']['total'] ?? 0, $commercial['currency'] ?? 'NIO', $commercial['exchange_rate'] ?? 1);
        $row = [
            'id' => $order->id,
            'number' => $order->number,
            'store_id' => $order->store_id,
            'client_name' => $order->client_name,
            'status' => $order->status,
            'delivery_at' => CarbonImmutable::parse($order->delivery_at, 'UTC')->toISOString(),
            'delivery_local' => $order->delivery_local,
            'delivery_timezone' => $order->delivery_timezone,
            'overdue' => $overdue,
            'items' => $items->all(),
            'pending_production' => $pendingProduction,
            'pending_delivery' => $pendingDelivery,
            'materials' => $material['public'],
            'payments' => [
                'currency' => $payments['currency'],
                'total' => $payments['total'],
                'paid' => $payments['paid'],
                'refunded' => $payments['refunded'],
                'pending' => $payments['pending'],
                'net_paid_nio' => $payments['net_paid_nio'],
                'pending_nio' => $payments['pending'] > 0 ? $payments['balance_nio'] : 0,
            ],
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->invoice_status,
                'grand_total' => (float) $invoice->grand_total,
            ] : null,
        ];

        if ($costs) {
            $budgetCost = (float) ($order->snapshot['totals']['cost'] ?? 0);
            $invoiceCost = $invoice?->invoiceDetails->sum(fn ($line) => (float) $line->cost);
            $row['financial'] = [
                'currency' => $commercial['currency'] ?? 'NIO',
                'budget_cost' => $budgetCost,
                'budget_cost_nio' => $this->nio($budgetCost, $commercial['currency'] ?? 'NIO', $commercial['exchange_rate'] ?? 1),
                'revenue_nio' => $revenueNio,
                'actual_material_cost_nio' => $material['actual_cost_nio'],
                'invoiced_cost' => $invoiceCost === null ? null : (float) $invoiceCost,
                'estimated_margin_nio' => Math::number(Math::of($revenueNio)->minus(Math::of($this->nio($budgetCost, $commercial['currency'] ?? 'NIO', $commercial['exchange_rate'] ?? 1))), 2),
            ];
        }

        return $row;
    }

    private function materials(CustomJobOrder $order): array
    {
        $allocations = CustomJobMaterialAllocation::where('order_id', $order->id)->get();
        $details = InventoryDetail::whereIn('id', $allocations->pluck('inventory_detail_id'))
            ->with(['inventory.store'])
            ->get()
            ->keyBy('id');
        $assigned = 0;
        $used = 0;
        $returned = 0;
        $remaining = 0;
        $recovered = 0;
        $actual = Math::of(0);
        $sources = [];
        $byUnit = [];
        foreach ($allocations as $allocation) {
            $balance = $this->materials->balance($allocation);
            $assigned += $balance['assigned'];
            $used += $balance['used'];
            $returned += $balance['returned'];
            $remaining += $balance['remaining'];
            $recovered += $balance['recovered'];
            $actual = $actual->plus(Math::of($balance['used'])->multipliedBy(Math::of($allocation->unit_cost_nio)));
            $unit = $allocation->base_unit;
            $byUnit[$unit] ??= ['unit' => $unit, 'assigned' => 0, 'used' => 0, 'returned' => 0, 'remaining' => 0, 'recovered' => 0];
            foreach (['assigned', 'used', 'returned', 'remaining', 'recovered'] as $key) {
                $byUnit[$unit][$key] += $balance[$key];
            }
            $detail = $details->get($allocation->inventory_detail_id);
            $store = $detail?->inventory?->store;
            if ($store) {
                $sources[$store->id] = ['id' => $store->id, 'name' => $store->name];
            }
        }

        return [
            'public' => [
                'assigned' => Math::number(Math::of($assigned), 6),
                'used' => Math::number(Math::of($used), 6),
                'returned' => Math::number(Math::of($returned), 6),
                'remaining' => Math::number(Math::of($remaining), 6),
                'recovered' => Math::number(Math::of($recovered), 6),
                'by_unit' => array_values(array_map(fn ($row) => [...$row, 'assigned' => Math::number(Math::of($row['assigned']), 6), 'used' => Math::number(Math::of($row['used']), 6), 'returned' => Math::number(Math::of($row['returned']), 6), 'remaining' => Math::number(Math::of($row['remaining']), 6), 'recovered' => Math::number(Math::of($row['recovered']), 6)], $byUnit)),
                'source_stores' => array_values($sources),
            ],
            'actual_cost_nio' => Math::number($actual, 2),
        ];
    }

    private function summary(Collection $rows, bool $costs): array
    {
        $summary = [
            'orders' => $rows->count(),
            'open' => $rows->where('status', 'confirmed')->where('pending_delivery', '>', 0)->count(),
            'overdue' => $rows->where('overdue', true)->count(),
            'completed' => $rows->filter(fn ($row) => $row['status'] !== 'cancelled' && $row['pending_delivery'] <= 0)->count(),
            'cancelled' => $rows->where('status', 'cancelled')->count(),
            'ordered_units' => $rows->sum(fn ($row) => collect($row['items'])->sum('ordered')),
            'produced_units' => $rows->sum(fn ($row) => collect($row['items'])->sum('produced')),
            'delivered_units' => $rows->sum(fn ($row) => collect($row['items'])->sum('delivered_net')),
            'pending_units' => $rows->sum('pending_delivery'),
            'payments_paid_nio' => $rows->sum('payments.net_paid_nio'),
            'payments_pending_nio' => $rows->sum('payments.pending_nio'),
        ];
        if ($costs) {
            $summary += [
                'budget_cost_nio' => $rows->sum('financial.budget_cost_nio'),
                'actual_material_cost_nio' => $rows->sum('financial.actual_material_cost_nio'),
                'revenue_nio' => $rows->sum('financial.revenue_nio'),
                'estimated_margin_nio' => $rows->sum('financial.estimated_margin_nio'),
            ];
        }

        return $summary;
    }

    private function dueFilter(Collection $rows, string $state): Collection
    {
        return match ($state) {
            'pending' => $rows->filter(fn ($row) => $row['status'] !== 'cancelled' && $row['pending_delivery'] > 0),
            'overdue' => $rows->filter(fn ($row) => $row['overdue']),
            'completed' => $rows->filter(fn ($row) => $row['status'] !== 'cancelled' && $row['pending_delivery'] <= 0),
            'cancelled' => $rows->where('status', 'cancelled'),
            default => $rows,
        };
    }

    private function canSeeCosts(User $user): bool
    {
        return $user->roles()->where('organization_id', TenantManager::getTenantId())
            ->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
    }

    private function nio(float $amount, string $currency, float $rate): float
    {
        return Math::number(Math::of($amount)->multipliedBy(Math::of($currency === 'USD' ? $rate : 1)), 2);
    }
}
