<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobOrderRevisions;
use App\Services\CustomJobOrders;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomJobOrderRevisionController extends Controller
{
    private function input(Request $r, bool $apply): array
    {
        $d = $r->validate(['version' => ['required', 'integer', 'min:1'], 'request_id' => [$apply ? 'required' : 'sometimes', 'uuid'], 'preview_digest' => [$apply ? 'required' : 'sometimes', 'string', 'size:64'],
            'reason' => [$apply ? 'required' : 'present', 'string', 'max:2000'], 'delivery_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'currency' => ['sometimes', Rule::in(['NIO', 'USD'])], 'discount' => ['sometimes', 'array:method,value'], 'discount.method' => ['required_with:discount', Rule::in(['amount', 'percent'])],
            'discount.value' => ['required_with:discount', 'numeric', 'min:0', 'max:100000000'], 'tax_amount' => ['sometimes', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['array:id,quantity,name,composition,price'], 'items.*.id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'items.*.name' => ['sometimes', 'string', 'max:120'], 'items.*.composition' => ['sometimes', 'array'],
            'items.*.price' => ['sometimes', 'array:method,unit_price'], 'items.*.price.method' => ['required_with:items.*.price', Rule::in(['keep', 'calculated', 'explicit'])],
            'items.*.price.unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,8'],
            'returns' => ['present', 'array', 'max:1000'], 'returns.*' => ['array:allocation_id,quantity,inventory_detail_id'], 'returns.*.allocation_id' => ['required', 'uuid'],
            'returns.*.quantity' => ['required', 'numeric', 'min:0', 'max:99999999999999', 'decimal:0,6'], 'returns.*.inventory_detail_id' => ['nullable', 'uuid'],
            'selections' => ['present', 'array', 'max:1000'], 'selections.*' => ['array:item_id,material_index,sources'], 'selections.*.item_id' => ['required', 'uuid'], 'selections.*.material_index' => ['required', 'integer', 'min:0'],
            'selections.*.sources' => ['required', 'array', 'min:1', 'max:100'], 'selections.*.sources.*' => ['array:inventory_detail_id,quantity'],
            'selections.*.sources.*.inventory_detail_id' => ['required', 'uuid'], 'selections.*.sources.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999999', 'decimal:0,6'],
            'excess' => ['present', 'array', 'max:100'], 'excess.*' => [Rule::in(['hold', 'discard'])],
            'restore_excess' => ['present', 'array', 'max:100'], 'restore_excess.*' => ['array:source_event_id,quantity'], 'restore_excess.*.source_event_id' => ['required', 'uuid'],
            'restore_excess.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000']]);
        $d['version'] = (int) $d['version'];
        foreach ($d['items'] as &$i) {
            $i['quantity'] = (int) $i['quantity'];
        }unset($i);

        return $d;
    }

    public function history(Request $r, string $id, CustomJobOrders $orders, CustomJobOrderRevisions $revisions)
    {
        return response()->json(['data' => $revisions->history($r->user(), $orders->accessible($r->user(), $id))])->header('Cache-Control', 'no-store');
    }

    public function preview(Request $r, string $id, CustomJobOrderRevisions $revisions)
    {
        return response()->json(['data' => $revisions->preview($r->user(), $id, $this->input($r, false))])->header('Cache-Control', 'no-store');
    }

    public function apply(Request $r, string $id, CustomJobOrders $orders, CustomJobOrderRevisions $revisions)
    {
        $order = $revisions->apply($r->user(), $id, $this->input($r, true));

        return response()->json(['data' => $orders->representation($r->user(), $order), 'revisions' => $revisions->history($r->user(), $order)])->header('Cache-Control', 'no-store');
    }
}
