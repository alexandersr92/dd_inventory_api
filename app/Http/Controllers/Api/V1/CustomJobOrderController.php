<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomJobOrder;
use App\Services\CustomJobCalculationContext;
use App\Services\CustomJobOrders;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomJobOrderController extends Controller
{
    private function result(Request $request, CustomJobOrders $service, CustomJobOrder $order, int $status = 200)
    {
        return response()->json(['data' => $service->representation($request->user(), $order)], $status)->header('Cache-Control', 'no-store');
    }

    public function index(Request $request, CustomJobCalculationContext $context, CustomJobOrders $service)
    {
        $data = $request->validate(['store_id' => ['required', 'uuid'], 'status' => ['nullable', Rule::in(['all', 'draft', 'confirmed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
            'delivery_from' => ['nullable', 'date_format:Y-m-d'], 'delivery_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('delivery_from') ? ['after_or_equal:delivery_from'] : [])]]);
        $store = $context->store($request->user(), $data['store_id']);
        $rows = CustomJobOrder::where('store_id', $store->id)
            ->when(($data['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $data['status']))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$s.'%')->orWhere('client_name', 'like', '%'.$s.'%')))
            ->when($data['delivery_from'] ?? null, fn ($q, $date) => $q->where('delivery_local', '>=', $date.'T00:00'))
            ->when($data['delivery_to'] ?? null, fn ($q, $date) => $q->where('delivery_local', '<=', $date.'T23:59'))
            ->with('items')->orderBy('delivery_at')->orderBy('id')->paginate(25);

        return response()->json(['data' => $rows->getCollection()->map(fn ($o) => $service->representation($request->user(), $o)),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'timezone' => $store->custom_job_timezone])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $id, CustomJobOrders $service)
    {
        return $this->result($request, $service, $service->accessible($request->user(), $id));
    }

    private function commitmentData(Request $request)
    {
        return $request->validate(['version' => ['required', 'integer', 'min:1'], 'delivery_local' => ['required', 'date_format:Y-m-d\TH:i']]);
    }

    public function convert(Request $request, string $id, CustomJobOrders $service)
    {
        $data = $this->commitmentData($request);
        $data += $request->validate(['revision_id' => ['required', 'uuid'], 'snapshot' => ['prohibited'], 'commercial' => ['prohibited'], 'total' => ['prohibited'], 'articles' => ['prohibited'], 'store_id' => ['prohibited'], 'client_id' => ['prohibited'], 'delivery_timezone' => ['prohibited']]);

        return $this->result($request, $service, $service->convert($request->user(), $id, $data), 201);
    }

    public function commitment(Request $request, string $id, CustomJobOrders $service)
    {
        return $this->result($request, $service, $service->commitment($request->user(), $id, $this->commitmentData($request)));
    }

    public function confirm(Request $request, string $id, CustomJobOrders $service)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return $this->result($request, $service, $service->confirm($request->user(), $id, $data['version'], CustomJobMaterialController::selection($request)));
    }
}
