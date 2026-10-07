<?php

namespace App\Services;

use App\Models\CustomJobMachine;
use App\Models\CustomJobProcess;
use App\Models\CustomJobRate;
use App\Support\CustomJobDecimals;
use App\Support\CustomJobMath as Math;
use Illuminate\Validation\ValidationException;

class CustomJobResourcePreview
{
    public function calculate(array $input): array
    {
        return $this->evaluate($input)['result'];
    }

    /** Internal exact values are consumed by article costing, never sent as JSON. */
    public function evaluate(array $input): array
    {
        $rate = isset($input['rate_id']) ? CustomJobRate::findOrFail($input['rate_id']) : null;
        $process = $rate ? CustomJobProcess::findOrFail($rate->process_id) : null;
        $machine = isset($input['machine_id']) ? CustomJobMachine::findOrFail($input['machine_id']) : null;
        foreach (['rate_id' => $rate, 'process_id' => $process, 'machine_id' => $machine] as $field => $resource) {
            if ($resource && $resource->status !== 'active') {
                throw ValidationException::withMessages([$field => 'Este recurso está archivado. Selecciona uno activo.']);
            }
        }
        $c = $rate?->configuration ?? [];
        $included = $c['included'] ?? [];
        $covers = fn ($concept) => in_array($concept, $included, true);
        $zero = Math::of(0);
        $labor = $zero;
        $groups = [];
        foreach ($input['people'] as $group) {
            $raw = Math::of($group['amount'])->multipliedBy($group['method'] === 'hour'
                ? Math::of($group['count'])->multipliedBy(Math::of($group['minutes']))->dividedBy(60)
                : Math::of($group['scope'] === 'person' ? $group['count'] : 1));
            $cost = $covers('operator') ? $zero : $raw;
            $groups[] = [...CustomJobDecimals::numbers($group), 'cost' => Math::number($cost), 'included_in_rate' => $covers('operator')];
            $labor = $labor->plus($cost);
        }
        $components = ['base' => $zero, 'maintenance' => $zero, 'electricity' => $zero];
        if ($machine && ! $covers('machine')) {
            $m = $machine->configuration;
            $minutes = Math::of($input['machine_minutes']);
            $base = $m['method'] === 'manual' ? Math::of($m['minute_rate'])
                : Math::of($m['purchase_value'])->minus(Math::of($m['residual_value']))->dividedBy(Math::of($m['useful_minutes']));
            $components = ['base' => $minutes->multipliedBy($base),
                'maintenance' => $covers('maintenance') ? $zero : $minutes->multipliedBy(Math::of($m['maintenance_per_minute'])),
                'electricity' => $covers('electricity') ? $zero : $minutes->multipliedBy(Math::of($m['energy_per_minute']))];
        }
        $machineCost = $components['base']->plus($components['maintenance'])->plus($components['electricity']);
        $processCost = $rate ? Math::of($c['amount'])->multipliedBy(Math::of($c['unit'] === 'job' ? 1 : $input['rate_quantity'])) : $zero;
        $setup = $rate ? Math::of($c['preparation_cost']) : $zero;
        $ink = $rate && ! $covers('ink') && $c['ink_unit'] !== 'none' ? Math::of($input['ink_quantity'])->multipliedBy(Math::of($c['ink_rate'])) : $zero;
        $exact = ['process_cost' => $processCost, 'preparation_cost' => $setup, 'machine_cost' => $machineCost, 'labor_cost' => $labor, 'ink_cost' => $ink];
        $total = $processCost->plus($setup)->plus($machineCost)->plus($labor)->plus($ink);

        return ['exact' => $exact, 'exact_total' => $total, 'result' => [
            ...array_map(fn ($v) => Math::number($v), $exact),
            'machine_breakdown' => array_map(fn ($v) => Math::number($v), $components),
            'people' => $groups, 'included' => $included, 'total' => Math::number($total),
            'snapshot' => ['schema_version' => 1, 'process' => $process?->toArray(), 'rate' => $rate?->toArray(), 'machine' => $machine?->toArray(), 'selection' => CustomJobDecimals::numbers($input)],
        ]];
    }
}
