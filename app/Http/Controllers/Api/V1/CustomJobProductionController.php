<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobOrders;
use App\Services\CustomJobProduction;
use Illuminate\Http\Request;

class CustomJobProductionController extends Controller
{
    public function show(Request $r, string $id, CustomJobOrders $orders, CustomJobProduction $production)
    {
        return response()->json(['data' => $production->view($r->user(), $orders->accessible($r->user(), $id))])->header('Cache-Control', 'no-store');
    }

    public function generate(Request $r, string $id, CustomJobOrders $orders, CustomJobProduction $production)
    {
        $d = $r->validate(['version' => ['required', 'integer', 'min:1']]);
        $production->generate($r->user(), $id, $d['version']);

        return $this->result($r, $id, $orders, $production);
    }

    public function record(Request $r, string $id, CustomJobOrders $orders, CustomJobProduction $production)
    {
        $d = $r->validate(['version' => ['required', 'integer', 'min:1'], 'request_id' => ['required', 'uuid'], 'item_id' => ['required', 'uuid'],
            'produced' => ['required', 'integer', 'min:0', 'max:1000000'], 'discarded' => ['required', 'integer', 'min:0', 'max:1000000'], 'occurred_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['present', 'nullable', 'string', 'max:2000'], 'corrects_event_id' => ['nullable', 'uuid'],
            'consumption' => ['present', 'array', 'max:1000'], 'consumption.*' => ['array:allocation_id,quantity'],
            'consumption.*.allocation_id' => ['required', 'uuid'], 'consumption.*.quantity' => ['required', 'numeric', 'gt:0']]);
        $d['note'] = $d['note'] ?? '';
        $production->record($r->user(), $id, $d);

        return $this->result($r, $id, $orders, $production);
    }

    private function result(Request $r, string $id, CustomJobOrders $orders, CustomJobProduction $production)
    {
        $order = $orders->accessible($r->user(), $id);

        return response()->json(['data' => $orders->representation($r->user(), $order), 'production' => $production->view($r->user(), $order)])->header('Cache-Control', 'no-store');
    }
}
