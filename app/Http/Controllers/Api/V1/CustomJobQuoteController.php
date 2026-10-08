<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CustomJobQuote;
use App\Services\CustomJobCalculationContext;
use App\Services\CustomJobQuotes;
use App\Services\TenantManager;
use App\Support\CustomJobCalculationInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomJobQuoteController extends Controller
{
    public function index(Request $request, CustomJobCalculationContext $context, CustomJobQuotes $quotes)
    {
        $data = $request->validate(['store_id' => ['required', 'uuid'], 'status' => ['nullable', Rule::in(['draft', 'issued', 'converted', 'all'])],
            'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d']]);
        $context->store($request->user(), $data['store_id']);
        $rows = CustomJobQuote::where('store_id', $data['store_id'])
            ->when(($data['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $data['status']))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$s.'%')->orWhere('client_name', 'like', '%'.$s.'%')))
            ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')->orderBy('id')->paginate(25);

        return response()->json(['data' => $rows->getCollection()->map(fn ($r) => $quotes->representation($request->user(), $r, false)),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()])->header('Cache-Control', 'no-store');
    }

    public function clients(Request $request, CustomJobCalculationContext $context)
    {
        $data = $request->validate(['store_id' => ['required', 'uuid'], 'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $context->store($request->user(), $data['store_id']);

        return response()->json(Client::where('status', 'active')->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name', 'cedula_ruc']))->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $id, CustomJobQuotes $quotes)
    {
        return response()->json(['data' => $quotes->representation($request->user(), $quotes->accessible($request->user(), $id))])->header('Cache-Control', 'no-store');
    }

    private function data(Request $request, bool $update): array
    {
        $data = $request->validate(['client_id' => ['nullable', 'uuid'], 'client_name' => ['required', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:255'],
            'input' => ['required', 'array:store_id,currency,articles,discount,tax_amount'],
            'request_id' => [$update ? 'prohibited' : 'required', 'uuid'],
            'version' => [$update ? 'required' : 'prohibited', 'integer', 'min:1'],
            'total' => ['prohibited'], 'grand_total' => ['prohibited'], 'snapshot' => ['prohibited']]);
        $data['input'] = CustomJobCalculationInput::validate($data['input']);

        return $data;
    }

    public function store(Request $request, CustomJobQuotes $quotes)
    {
        $row = $quotes->save($request->user(), $this->data($request, false));

        return response()->json(['data' => $quotes->representation($request->user(), $row)], 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $id, CustomJobQuotes $quotes)
    {
        $row = $quotes->save($request->user(), $this->data($request, true), $id);

        return response()->json(['data' => $quotes->representation($request->user(), $row)])->header('Cache-Control', 'no-store');
    }

    public function issue(Request $request, string $id, CustomJobQuotes $quotes)
    {
        abort_unless($request->user()->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'invoice.store'))->exists(), 403, 'Necesitas permiso para crear proformas.');
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'revision_id' => ['required', 'uuid']]);

        return response()->json(['data' => $quotes->representation($request->user(), $quotes->issue($request->user(), $id, $data))])->header('Cache-Control', 'no-store');
    }

    public function duplicate(Request $request, string $id, CustomJobQuotes $quotes)
    {
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'revision_id' => ['required', 'uuid']]);
        $row = $quotes->duplicate($request->user(), $id, $data);

        return response()->json(['data' => $quotes->representation($request->user(), $row)], 201)->header('Cache-Control', 'no-store');
    }
}
