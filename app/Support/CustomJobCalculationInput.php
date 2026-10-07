<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shared input validation for preview and future quote/order revisions. */
final class CustomJobCalculationInput
{
    public static function validate(array $input): array
    {
        $input = CustomJobDecimals::expand($input);
        $positive = ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,6'];
        $nonnegative = ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,6'];
        $money = ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,8'];
        $data = Validator::make($input, [
            'store_id' => ['required', 'uuid'], 'currency' => ['required', Rule::in(['NIO', 'USD'])],
            'discount' => ['required', 'array:method,value'], 'discount.method' => ['required', Rule::in(['percent', 'amount'])], 'discount.value' => $money,
            'tax_amount' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'articles' => ['required', 'array', 'min:1', 'max:100'], 'articles.*' => ['array:name,quantity,materials,operations,profit'],
            'articles.*.name' => ['required', 'string', 'max:120'], 'articles.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'articles.*.profit' => ['required', 'array:method,value,scope'], 'articles.*.profit.method' => ['required', Rule::in(['percent', 'amount'])],
            'articles.*.profit.value' => $money, 'articles.*.profit.scope' => ['required', Rule::in(['unit', 'batch'])],
            'articles.*.materials' => ['present', 'array', 'max:100'],
            'articles.*.materials.*' => ['array:inventory_detail_id,method,amount,width,height,applications,measure_unit,quantity_source,loss'],
            'articles.*.materials.*.inventory_detail_id' => ['required', 'uuid'],
            'articles.*.materials.*.method' => ['required', Rule::in(['unit', 'area', 'length'])],
            'articles.*.materials.*.amount' => $positive, 'articles.*.materials.*.width' => $positive, 'articles.*.materials.*.height' => $positive,
            'articles.*.materials.*.applications' => ['required', 'integer', 'min:1', 'max:1000000'],
            'articles.*.materials.*.measure_unit' => ['required', Rule::in(['mm', 'cm', 'm'])],
            'articles.*.materials.*.quantity_source' => ['required', Rule::in(['base', 'consumption', 'purchase'])],
            'articles.*.materials.*.loss' => ['required', 'array:method,value'],
            'articles.*.materials.*.loss.method' => ['required', Rule::in(['none', 'additional', 'yield'])], 'articles.*.materials.*.loss.value' => $money,
            'articles.*.operations' => ['present', 'array', 'max:50'], 'articles.*.operations.*' => ['array:selection,machine_setup_minutes'],
            'articles.*.operations.*.machine_setup_minutes' => $nonnegative,
            'articles.*.operations.*.selection' => ['required', 'array:rate_id,machine_id,rate_quantity,machine_minutes,ink_quantity,people'],
            'articles.*.operations.*.selection.rate_id' => ['nullable', 'uuid'], 'articles.*.operations.*.selection.machine_id' => ['nullable', 'uuid'],
            'articles.*.operations.*.selection.rate_quantity' => $nonnegative, 'articles.*.operations.*.selection.machine_minutes' => $nonnegative,
            'articles.*.operations.*.selection.ink_quantity' => $nonnegative, 'articles.*.operations.*.selection.people' => ['present', 'array', 'max:50'],
            'articles.*.operations.*.selection.people.*' => ['array:count,method,scope,amount,minutes,setup_minutes'],
            'articles.*.operations.*.selection.people.*.count' => ['required', 'integer', 'min:1', 'max:1000'],
            'articles.*.operations.*.selection.people.*.method' => ['required', Rule::in(['hour', 'work'])],
            'articles.*.operations.*.selection.people.*.scope' => ['required', Rule::in(['person', 'team'])],
            'articles.*.operations.*.selection.people.*.amount' => $money, 'articles.*.operations.*.selection.people.*.minutes' => $nonnegative,
            'articles.*.operations.*.selection.people.*.setup_minutes' => $nonnegative,
        ])->validate();
        $data = CustomJobDecimals::numbers($data);
        foreach ($data['articles'] as $i => $article) {
            foreach ($article['materials'] as $j => $material) {
                $loss = $material['loss'];
                if (($loss['method'] === 'yield' && ($loss['value'] <= 0 || $loss['value'] > 1)) || ($loss['method'] === 'none' && $loss['value'] != 0)) {
                    throw ValidationException::withMessages(["articles.$i.materials.$j.loss.value" => 'Rendimiento debe ser mayor que 0 y hasta 1; sin pérdida el valor debe ser 0.']);
                }
            }
        }
        if ($data['discount']['method'] === 'percent' && $data['discount']['value'] > 100) {
            throw ValidationException::withMessages(['discount.value' => 'El porcentaje de descuento no puede superar 100.']);
        }

        return $data;
    }
}
