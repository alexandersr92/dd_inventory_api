<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomJobTemplate;
use App\Services\CustomJobCalculationContext;
use App\Services\CustomJobCalculator;
use App\Support\CustomJobCalculationInput;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomJobTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->validate(['status' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $rows = CustomJobTemplate::query()->when(($query['status'] ?? 'active') !== 'all', fn ($q) => $q->where('status', $query['status'] ?? 'active'))
            ->when($query['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->orderBy('name')->orderBy('id')->paginate(25);

        return response()->json($rows)->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, CustomJobCalculationContext $context, CustomJobCalculator $calculator)
    {
        return $this->save($request, new CustomJobTemplate, $context, $calculator);
    }

    public function update(Request $request, string $id, CustomJobCalculationContext $context, CustomJobCalculator $calculator)
    {
        return $this->save($request, CustomJobTemplate::findOrFail($id), $context, $calculator);
    }

    private function save(Request $request, CustomJobTemplate $row, CustomJobCalculationContext $context, CustomJobCalculator $calculator)
    {
        $meta = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'],
            'composition' => ['required', 'array:currency,articles,discount,tax_amount'], 'store_id' => ['required', 'uuid']]);
        $input = CustomJobCalculationInput::validate([...$meta['composition'], 'store_id' => $meta['store_id']]);
        $store = $context->store($request->user(), $input['store_id']);
        $commercial = $context->commercial($store, $input['currency']);
        $created = ! $row->exists;
        DB::transaction(function () use ($request, $row, $meta, $input, $commercial, $context, $calculator) {
            // Saving rejects unavailable sources and invalid conversions; costs remain authoritative and unstored.
            $calculator->calculate($input, $commercial, $context->inventories($request->user())->pluck('id')->all());
            unset($input['store_id']);
            $row->fill(['name' => $meta['name'], 'description' => $meta['description'] ?? null, 'composition' => $input])->save();
        });

        return response()->json(['data' => $row->fresh()], $created ? 201 : 200)->header('Cache-Control', 'no-store');
    }

    public function archive(string $id)
    {
        $row = CustomJobTemplate::findOrFail($id);
        $row->update(['status' => 'archived']);

        return response()->json(['data' => $row->fresh()])->header('Cache-Control', 'no-store');
    }

    public function instantiate(Request $request, string $id, CustomJobCalculationContext $context, CustomJobCalculator $calculator)
    {
        $row = CustomJobTemplate::findOrFail($id);
        abort_unless($row->status === 'active', 422, 'La plantilla está archivada.');
        $data = $request->validate(['store_id' => ['required', 'uuid']]);
        $store = $context->store($request->user(), $data['store_id']);
        $input = CustomJobCalculationInput::validate([...$row->composition, 'store_id' => $store->id]);
        $result = null;
        $warnings = [];
        // A failed source never disappears from the editable copy and never receives a zero-cost fallback.
        DB::transaction(function () use ($request, $context, $calculator, $store, $input, &$result, &$warnings) {
            $inventoryIds = $context->inventories($request->user())->pluck('id')->all();
            try {
                $commercial = $context->commercial($store, $input['currency']);
            } catch (ValidationException $e) {
                $warnings = $e->errors();

                return;
            }
            foreach ($input['articles'] as $i => $article) {
                foreach (['materials', 'operations'] as $kind) {
                    foreach ($article[$kind] as $j => $component) {
                        $single = [...$article, 'materials' => [], 'operations' => [], 'profit' => ['method' => 'percent', 'value' => 0, 'scope' => 'batch']];
                        $single[$kind] = [$component];
                        try {
                            $calculator->calculate([...$input, 'articles' => [$single], 'discount' => ['method' => 'amount', 'value' => 0], 'tax_amount' => 0], $commercial, $inventoryIds);
                        } catch (ModelNotFoundException $e) {
                            $warnings["articles.$i.$kind.$j"] = ['Fuente no disponible en esta cuenta o en tus inventarios autorizados. Selecciona una fuente activa.'];
                        } catch (ValidationException $e) {
                            $warnings["articles.$i.$kind.$j"] = array_merge(...array_values($e->errors()));
                        }
                    }
                }
            }
            if (! $warnings) {
                try {
                    $result = $calculator->calculate($input, $commercial, $inventoryIds);
                } catch (ValidationException $e) {
                    $warnings = $e->errors();
                }
            }
        });

        return response()->json(['data' => ['template_id' => $row->id, 'input' => $input, 'warnings' => $warnings ?: (object) [], 'calculation' => $result]])->header('Cache-Control', 'no-store');
    }
}
