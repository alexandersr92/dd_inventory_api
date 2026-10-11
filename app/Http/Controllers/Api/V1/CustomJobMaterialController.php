<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobMaterials;
use App\Services\CustomJobOrders;
use Illuminate\Http\Request;

class CustomJobMaterialController extends Controller
{
    public static function selection(Request $request): array
    {
        return $request->validate(['selections' => ['sometimes', 'array', 'max:1000'],
            'selections.*' => ['array:item_id,material_index,target,sources'],
            'selections.*.item_id' => ['required', 'uuid'], 'selections.*.material_index' => ['required', 'integer', 'min:0'],
            'selections.*.target' => ['sometimes', 'numeric', 'min:0'],
            'selections.*.sources' => ['required', 'array', 'max:100'],
            'selections.*.sources.*' => ['array:inventory_detail_id,quantity'],
            'selections.*.sources.*.inventory_detail_id' => ['required', 'uuid'], 'selections.*.sources.*.quantity' => ['required', 'numeric', 'gt:0']])['selections'] ?? [];
    }

    public function show(Request $request, string $id, CustomJobOrders $orders, CustomJobMaterials $materials)
    {
        return response()->json(['data' => $materials->view($request->user(), $orders->accessible($request->user(), $id))])->header('Cache-Control', 'no-store');
    }

    public function mutate(Request $request, string $id, string $kind, CustomJobOrders $orders, CustomJobMaterials $materials)
    {
        abort_unless(in_array($kind, ['additional', 'return', 'recover'], true), 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'request_id' => ['required', 'uuid']]);
        if ($kind === 'additional') {
            $lines = self::selection($request);
            abort_unless($lines, 422, 'Indica los materiales a ampliar.');
            foreach ($lines as $line) {
                abort_unless(isset($line['target']), 422, 'Indica el objetivo total de material.');
            }
        } else {
            $lines = $request->validate(['lines' => ['required', 'array', 'min:1', 'max:1000'],
                'lines.*' => ['array:allocation_id,quantity,inventory_detail_id,source_event_id'],
                'lines.*.allocation_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'numeric', 'min:0'],
                'lines.*.inventory_detail_id' => ['nullable', 'uuid'], 'lines.*.source_event_id' => [$kind === 'recover' ? 'required' : 'prohibited', 'uuid']])['lines'];
        }
        $materials->mutate($request->user(), $id, $data['version'], $data['request_id'], $kind, $lines);
        $order = $orders->accessible($request->user(), $id);

        return response()->json(['data' => $orders->representation($request->user(), $order), 'materials' => $materials->view($request->user(), $order)])->header('Cache-Control', 'no-store');
    }
}
