<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobCancellations;
use App\Services\CustomJobOrders;
use Illuminate\Http\Request;

final class CustomJobCancellationController extends Controller
{
    private function input(Request $request, bool $apply = false): array
    {
        $data = $request->validate([
            'operation' => ['sometimes', 'in:cancel,annul_payment'],
            'payment_id' => ['nullable', 'uuid'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'digest' => [$apply ? 'required' : 'nullable', 'string', 'size:64'],
            'request_id' => [$apply ? 'required' : 'nullable', 'uuid'],
            'returns' => ['sometimes', 'array', 'max:1000'],
            'returns.*' => ['array:allocation_id,quantity,inventory_detail_id'],
            'returns.*.allocation_id' => ['required', 'uuid'],
            'returns.*.quantity' => ['required', 'numeric', 'min:0'],
            'returns.*.inventory_detail_id' => ['nullable', 'uuid'],
            'recoveries' => ['sometimes', 'array', 'max:1000'],
            'recoveries.*' => ['array:allocation_id,source_event_id,quantity,inventory_detail_id'],
            'recoveries.*.allocation_id' => ['required', 'uuid'],
            'recoveries.*.source_event_id' => ['required', 'uuid'],
            'recoveries.*.quantity' => ['required', 'numeric', 'min:0'],
            'recoveries.*.inventory_detail_id' => ['nullable', 'uuid'],
        ]);
        if (trim($data['reason']) === '') {
            abort(422, 'Indica el motivo de la cancelación.');
        }
        if (($data['operation'] ?? 'cancel') === 'annul_payment' && empty($data['payment_id'])) {
            abort(422, 'Selecciona el cobro que se anulará.');
        }
        $data['operation'] = $data['operation'] ?? 'cancel';

        return $data;
    }

    public function preview(Request $request, string $id, CustomJobCancellations $cancellations)
    {
        return response()->json(['data' => $cancellations->preview($request->user(), $id, $this->input($request))])->header('Cache-Control', 'no-store');
    }

    public function apply(Request $request, string $id, CustomJobCancellations $cancellations, CustomJobOrders $orders)
    {
        $data = $this->input($request, true);
        if ($request->route('operation')) {
            $data['operation'] = $request->route('operation');
        }
        $order = $cancellations->apply($request->user(), $id, $data);

        return response()->json(['data' => $orders->representation($request->user(), $order), 'history' => $cancellations->history($request->user(), $id)])->header('Cache-Control', 'no-store');
    }

    public function history(Request $request, string $id, CustomJobCancellations $cancellations)
    {
        return response()->json(['data' => $cancellations->history($request->user(), $id)])->header('Cache-Control', 'no-store');
    }
}
