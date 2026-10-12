<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobDeliveries;
use App\Services\CustomJobOrders;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomJobDeliveryController extends Controller
{
    public function show(Request $r, string $id, CustomJobOrders $orders, CustomJobDeliveries $deliveries)
    {
        return response()->json(['data' => $deliveries->view($orders->accessible($r->user(), $id))])->header('Cache-Control', 'no-store');
    }

    public function record(Request $r, string $id, string $kind, CustomJobOrders $orders, CustomJobDeliveries $deliveries)
    {
        abort_unless(in_array($kind, ['delivery', 'return', 'correct_delivery', 'correct_return'], true), 404);
        $d = $r->validate(['version' => ['required', 'integer', 'min:1'], 'request_id' => ['required', 'uuid'], 'occurred_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['present', 'nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:item_id,quantity,source_line_id,disposition'],
            'lines.*.item_id' => [$kind === 'delivery' ? 'required' : 'prohibited', 'uuid'],
            'lines.*.quantity' => [in_array($kind, ['delivery', 'return'], true) ? 'required' : 'prohibited', 'integer', 'min:1', 'max:1000000'],
            'lines.*.source_line_id' => [$kind === 'delivery' ? 'prohibited' : 'required', 'uuid'],
            'lines.*.disposition' => [$kind === 'return' ? 'required' : 'prohibited', Rule::in(['ready', 'hold', 'discard'])]]);
        $d['note'] = $d['note'] ?? '';
        $deliveries->record($r->user(), $id, $kind, $d);
        $order = $orders->accessible($r->user(), $id);

        return response()->json(['data' => $orders->representation($r->user(), $order), 'delivery' => $deliveries->view($order)])->header('Cache-Control', 'no-store');
    }
}
