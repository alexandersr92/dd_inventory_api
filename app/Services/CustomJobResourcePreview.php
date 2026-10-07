<?php

namespace App\Services;

use App\Models\CustomJobMachine;
use App\Models\CustomJobProcess;
use App\Models\CustomJobRate;
use Illuminate\Validation\ValidationException;

/** Resource cost preview only. Full article costing and commercial rounding belong to plan 04. */
class CustomJobResourcePreview
{
    public function calculate(array $input): array
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
        $labor = 0;
        $groups = [];
        foreach ($input['people'] as $group) {
            $raw = $group['method'] === 'hour'
                ? $group['count'] * ($group['minutes'] / 60) * $group['amount']
                : $group['amount'] * ($group['scope'] === 'person' ? $group['count'] : 1);
            $groups[] = [...$group, 'cost' => round($covers('operator') ? 0 : $raw, 8), 'included_in_rate' => $covers('operator')];
            $labor += $covers('operator') ? 0 : $raw;
        }
        $machineCost = 0;
        $machineBreakdown = ['base' => 0, 'maintenance' => 0, 'electricity' => 0];
        if ($machine && ! $covers('machine')) {
            $m = $machine->configuration;
            $minutes = $input['machine_minutes'];
            $base = $m['method'] === 'manual' ? $m['minute_rate'] : ($m['purchase_value'] - $m['residual_value']) / $m['useful_minutes'];
            $machineBreakdown = [
                'base' => round($minutes * $base, 8),
                'maintenance' => round($covers('maintenance') ? 0 : $minutes * $m['maintenance_per_minute'], 8),
                'electricity' => round($covers('electricity') ? 0 : $minutes * $m['energy_per_minute'], 8),
            ];
            $machineCost = array_sum($machineBreakdown);
        }
        $processCost = $rate ? (float) $c['amount'] * ($c['unit'] === 'job' ? 1 : $input['rate_quantity']) : 0;
        $setup = $rate ? (float) $c['preparation_cost'] : 0;
        $ink = $rate && ! $covers('ink') && $c['ink_unit'] !== 'none' ? $input['ink_quantity'] * $c['ink_rate'] : 0;
        $snapshot = [
            'schema_version' => 1,
            'process' => $process?->toArray(),
            'rate' => $rate?->toArray(),
            'machine' => $machine?->toArray(),
            'selection' => $input,
        ];

        return [
            'process_cost' => round($processCost, 8), 'preparation_cost' => round($setup, 8),
            'machine_cost' => round($machineCost, 8), 'machine_breakdown' => $machineBreakdown,
            'labor_cost' => round($labor, 8), 'ink_cost' => round($ink, 8), 'people' => $groups,
            'included' => $included, 'total' => round($processCost + $setup + $machineCost + $labor + $ink, 8),
            'snapshot' => $snapshot,
        ];
    }
}
