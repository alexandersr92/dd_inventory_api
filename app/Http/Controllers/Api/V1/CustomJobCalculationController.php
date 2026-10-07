<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryDetail;
use App\Services\CustomJobCalculationContext;
use App\Services\CustomJobCalculator;
use App\Support\CustomJobCalculationInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomJobCalculationController extends Controller
{
    public function materials(Request $request, CustomJobCalculationContext $context)
    {
        $data = $request->validate(['store_id' => ['required', 'uuid'], 'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $context->store($request->user(), $data['store_id']);
        $query = InventoryDetail::whereIn('inventory_id', $context->inventories($request->user())->select('id'))->where('status', 'active')
            ->whereHas('product', fn ($q) => $q->material()->where('status', 'active')->when($data['search'] ?? null, fn ($s, $term) => $s->where('name', 'like', '%'.$term.'%')))
            ->with(['product', 'inventory'])->orderBy('inventory_id')->orderBy('product_id')->orderBy('id')->paginate(50);

        return response()->json(['data' => $query->getCollection()->map(fn ($d) => [
            'id' => $d->id, 'product_id' => $d->product_id, 'inventory_id' => $d->inventory_id, 'inventory_name' => $d->inventory->name,
            'name' => $d->product->name, 'base_unit' => $d->product->unit_of_measure ?: 'unit', 'material_profile' => $d->product->material_profile,
            'base_unit_cost_nio' => (float) $d->product->getRawOriginal('cost'), 'available' => (float) $d->quantity,
        ]), 'last_page' => $query->lastPage(), 'current_page' => $query->currentPage()])->header('Cache-Control', 'no-store');
    }

    public function preview(Request $request, CustomJobCalculationContext $context, CustomJobCalculator $calculator)
    {
        $data = CustomJobCalculationInput::validate($request->all());
        $store = $context->store($request->user(), $data['store_id']);
        $commercial = $context->commercial($store, $data['currency']);
        // One read transaction ensures a consistent snapshot across all selected sources.
        $result = DB::transaction(fn () => $calculator->calculate($data, $commercial, $context->inventories($request->user())->pluck('id')->all()));

        return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
    }
}
