<?php

namespace App\Services;

use App\Models\InventoryDetail;
use App\Support\CustomJobMath as Math;
use Illuminate\Validation\ValidationException;

/** Budget costing only: never writes stock, invoices, payments or production. */
final class CustomJobCalculator
{
    public function __construct(private CustomJobResourcePreview $resources) {}

    public function calculate(array $input, array $commercial, array $inventoryIds, array $unitPrices = []): array
    {
        $divisor = Math::of($commercial['currency'] === 'USD' ? $commercial['usd_exchange_rate'] : 1);
        $costAll = Math::of(0);
        $subtotal = Math::of(0);
        $lines = [];
        $requirements = [];
        foreach ($input['articles'] as $i => $article) {
            $quantity = Math::of($article['quantity']);
            $materialCost = Math::of(0);
            $materials = [];
            foreach ($article['materials'] as $j => $material) {
                $detail = InventoryDetail::whereIn('inventory_id', $inventoryIds)->where('status', 'active')
                    ->with(['product', 'inventory'])->findOrFail($material['inventory_detail_id']);
                $product = $detail->product;
                if (! $product || $product->status !== 'active' || ! in_array($product->product_usage, ['supply', 'both'], true)) {
                    $this->fail("articles.$i.materials.$j", 'Selecciona un producto activo marcado como Insumo o Ambos.');
                }
                $profile = $product->material_profile;
                $base = MaterialUnits::canonical($product->unit_of_measure);
                if ($material['method'] === 'area') {
                    $cm = MaterialUnits::consumptionFactor($material['measure_unit'], 'cm', null);
                    $net = Math::of($material['width'])->multipliedBy($cm)->multipliedBy(Math::of($material['height']))->multipliedBy($cm)
                        ->multipliedBy(Math::of($material['applications']))->multipliedBy($quantity);
                    $unit = 'cm2';
                } elseif ($material['method'] === 'length') {
                    $net = Math::of($material['amount'])->multipliedBy($quantity);
                    $unit = $material['measure_unit'];
                } else {
                    $net = Math::of($material['amount'])->multipliedBy($quantity);
                    $unit = $material['quantity_source'] === 'base' ? $base : ($profile[$material['quantity_source'].'_unit'] ?? $base);
                }
                $factor = MaterialUnits::consumptionFactor($unit, $base, $profile);
                $loss = $material['loss'];
                $gross = match ($loss['method']) {
                    'additional' => $net->multipliedBy(Math::of(1)->plus(Math::of($loss['value'])->dividedBy(100))),
                    'yield' => $net->dividedBy(Math::of($loss['value'])),
                    default => $net,
                };
                $normalized = Math::number($gross->multipliedBy($factor), 6);
                if ($normalized <= 0 || $normalized >= 100000000000000) {
                    $this->fail("articles.$i.materials.$j", 'El consumo convertido está fuera de la precisión de inventario (6 decimales).');
                }
                // Read the stored base cost, preserving decimal text; never use retail/inventory price.
                $baseCost = Math::of($product->getRawOriginal('cost'));
                if ($baseCost->isNegative()) {
                    $this->fail("articles.$i.materials.$j", 'El costo del producto no puede ser negativo.');
                }
                $cost = Math::of($normalized)->multipliedBy($baseCost)->dividedBy($divisor);
                $materialCost = $materialCost->plus($cost);
                $row = ['inventory_detail_id' => $detail->id, 'inventory_id' => $detail->inventory_id, 'inventory_name' => $detail->inventory->name,
                    'product_id' => $product->id, 'name' => $product->name, 'base_unit' => $base, 'consumption_unit' => $unit,
                    'net_quantity' => Math::number($net), 'gross_quantity' => Math::number($gross), 'factor_to_base' => Math::number($factor, 12), 'factor_ratio' => (string) $factor,
                    'base_quantity' => $normalized, 'base_unit_cost_nio' => Math::number($baseCost), 'cost' => Math::number($cost),
                    'material_profile' => $profile, 'input' => $material];
                $materials[] = $row;
                if (! isset($requirements[$detail->id])) {
                    $requirements[$detail->id] = ['inventory_detail_id' => $detail->id, 'inventory_id' => $detail->inventory_id,
                        'product_id' => $product->id, 'name' => $product->name, 'base_unit' => $base, 'required' => 0, 'available' => (float) $detail->quantity];
                }
                $requirements[$detail->id]['required'] = Math::number(Math::of($requirements[$detail->id]['required'])->plus(Math::of($normalized)), 6);
            }
            $operationCost = Math::of(0);
            $breakdown = array_fill_keys(['process_cost', 'preparation_cost', 'machine_cost', 'labor_cost', 'ink_cost'], Math::of(0));
            $operations = [];
            foreach ($article['operations'] as $op) {
                $selection = $op['selection'];
                $selection['rate_id'] ??= null;
                $selection['machine_id'] ??= null;
                $selection['machine_minutes'] = (string) Math::of($selection['machine_minutes'])->multipliedBy($quantity)->plus(Math::of($op['machine_setup_minutes']))->toBigDecimal();
                foreach (['rate_quantity', 'ink_quantity'] as $field) {
                    $selection[$field] = (string) Math::of($selection[$field])->multipliedBy($quantity)->toBigDecimal();
                }
                foreach ($selection['people'] as &$group) {
                    if ($group['method'] === 'hour' && $group['scope'] !== 'person') {
                        $this->fail("articles.$i.operations", 'Pago por hora siempre es por persona.');
                    }
                    $group['minutes'] = (string) Math::of($group['minutes'])->multipliedBy($quantity)->plus(Math::of($group['setup_minutes']))->toBigDecimal();
                    unset($group['setup_minutes']);
                }
                unset($group);
                $evaluation = $this->resources->evaluate($selection);
                $operationCost = $operationCost->plus($evaluation['exact_total']->dividedBy($divisor));
                foreach ($breakdown as $key => $value) {
                    $breakdown[$key] = $value->plus($evaluation['exact'][$key]->dividedBy($divisor));
                }
                $operations[] = ['input' => $op, 'resources_nio' => $evaluation['result']];
            }
            $cost = $materialCost->plus($operationCost);
            $profit = $article['profit'];
            $gain = $profit['method'] === 'percent' ? $cost->multipliedBy(Math::of($profit['value']))->dividedBy(100)
                : Math::of($profit['value'])->multipliedBy($profit['scope'] === 'unit' ? $quantity : Math::of(1));
            $price = Math::of(Math::number(array_key_exists($i, $unitPrices) ? Math::of($unitPrices[$i])->multipliedBy($quantity) : $cost->plus($gain), 2));
            if ($price->isGreaterThan(10000000000) || $cost->isGreaterThan(10000000000)) {
                $this->fail("articles.$i", 'El importe calculado es demasiado grande.');
            }
            $costAll = $costAll->plus($cost);
            $subtotal = $subtotal->plus($price);
            $lines[] = ['name' => $article['name'], 'quantity' => $article['quantity'], 'materials' => $materials, 'operations' => $operations,
                'material_cost' => Math::number($materialCost), ...array_map(fn ($v) => Math::number($v), $breakdown),
                'cost' => Math::number($cost), 'unit_cost' => Math::number($cost->dividedBy($quantity)), 'profit' => $profit,
                'price' => Math::number($price, 2), 'unit_price' => array_key_exists($i, $unitPrices) ? Math::number(Math::of($unitPrices[$i])) : Math::number($price->dividedBy($quantity)),
                'planned_profit' => Math::number($price->minus($cost))];
        }
        $discount = $input['discount']['method'] === 'percent' ? $subtotal->multipliedBy(Math::of($input['discount']['value']))->dividedBy(100) : Math::of($input['discount']['value']);
        $discount = Math::of(Math::number($discount, 2));
        if ($discount->isGreaterThan($subtotal)) {
            $this->fail('discount.value', 'El descuento no puede superar el subtotal.');
        }
        $net = $subtotal->minus($discount);
        $tax = Math::of($input['tax_amount']);
        $total = $net->plus($tax);
        if ($total->isGreaterThan(10000000000)) {
            $this->fail('articles', 'El total calculado es demasiado grande.');
        }
        // Allocate discount by rounded line prices; final line receives rounding remainder.
        $remaining = $discount;
        foreach ($lines as $i => &$line) {
            $share = $subtotal->isZero() ? Math::of(0) : Math::of($line['price'])->multipliedBy($discount)->dividedBy($subtotal);
            $share = $i === count($lines) - 1 ? $remaining : Math::of(Math::number($share, 2));
            if ($share->isGreaterThan($remaining)) {
                $share = $remaining;
            }
            $remaining = $remaining->minus($share);
            $line['discount'] = Math::number($share, 2);
            $line['net_price'] = Math::number(Math::of($line['price'])->minus($share), 2);
            $line['final_profit'] = Math::number(Math::of($line['net_price'])->minus(Math::of($line['cost'])), 2);
        }
        unset($line);
        foreach ($requirements as &$requirement) {
            $requirement['shortage'] = max(0, Math::number(Math::of($requirement['required'])->minus(Math::of($requirement['available'])), 6));
        }
        unset($requirement);
        $totals = ['cost' => Math::number($costAll), 'subtotal' => Math::number($subtotal, 2), 'discount' => Math::number($discount, 2),
            'net' => Math::number($net, 2), 'tax' => Math::number($tax, 2), 'total' => Math::number($total, 2), 'final_profit' => Math::number($net->minus($costAll), 2)];

        return ['cost_type' => 'budget', 'policy' => Math::POLICY, 'commercial' => $commercial, 'articles' => $lines, 'materials' => array_values($requirements), 'totals' => $totals,
            'snapshot' => ['schema_version' => 1, 'policy' => Math::POLICY, 'commercial' => $commercial, 'input' => $input, 'articles' => $lines, 'totals' => $totals]];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
