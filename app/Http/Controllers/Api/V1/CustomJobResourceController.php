<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomJobMachine;
use App\Models\CustomJobProcess;
use App\Models\CustomJobRate;
use App\Services\TenantManager;
use App\Support\CustomJobDecimals;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomJobResourceController extends Controller
{
    private const MODELS = ['processes' => CustomJobProcess::class, 'machines' => CustomJobMachine::class, 'rates' => CustomJobRate::class];

    private function model(string $kind): string
    {
        abort_unless(isset(self::MODELS[$kind]), 404);

        return self::MODELS[$kind];
    }

    private function costs(Request $request): bool
    {
        return $request->user()->roles()->where('organization_id', TenantManager::getTenantId())
            ->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
    }

    public function index(Request $request, string $kind)
    {
        $model = $this->model($kind);
        $query = $request->validate(['status' => ['nullable', Rule::in(['active', 'archived', 'all'])]]);
        $rows = $model::query()->orderBy('name')->orderBy('id');
        if (($query['status'] ?? 'active') !== 'all') {
            $rows->where('status', $query['status'] ?? 'active');
        }
        $costs = $this->costs($request);

        return response()->json(['data' => $rows->get()->map(function ($row) use ($costs) {
            $data = $row->toArray();
            if (! $costs) {
                unset($data['configuration']);
            }

            return $data;
        })])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, string $kind)
    {
        $model = $this->model($kind);

        return response()->json(['data' => $model::create($this->validated($request, $kind))], 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $kind, string $id)
    {
        $model = $this->model($kind);
        $row = $model::findOrFail($id);
        $row->update($this->validated($request, $kind));

        return response()->json(['data' => $row->fresh()])->header('Cache-Control', 'no-store');
    }

    public function archive(string $kind, string $id)
    {
        $model = $this->model($kind);
        $row = $model::findOrFail($id);
        $row->update(['status' => 'archived']);

        return response()->json(['data' => $row->fresh()])->header('Cache-Control', 'no-store');
    }

    private function validated(Request $request, string $kind): array
    {
        $request->replace(CustomJobDecimals::expand($request->all()));
        $money = ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,8'];
        $rules = ['name' => ['required', 'string', 'max:120'], 'status' => ['required', Rule::in(['active', 'archived'])]];
        if ($kind === 'processes') {
            $rules += [
                'configuration' => ['required', 'array:kind'],
                'configuration.kind' => ['required', Rule::in(['cutting', 'printing', 'assembly', 'digital', 'other'])],
            ];
        } elseif ($kind === 'machines') {
            $rules += [
                'configuration' => ['required', 'array:method,minute_rate,purchase_value,residual_value,useful_minutes,maintenance_per_minute,energy_per_minute'],
                'configuration.method' => ['required', Rule::in(['manual', 'depreciation'])],
                'configuration.minute_rate' => $money,
                'configuration.purchase_value' => $money,
                'configuration.residual_value' => [...$money, 'lte:configuration.purchase_value'],
                'configuration.useful_minutes' => ['required', 'numeric', 'gt:0', 'max:100000000', 'decimal:0,6'],
                'configuration.maintenance_per_minute' => $money,
                'configuration.energy_per_minute' => $money,
            ];
        } else {
            $rules += [
                'process_id' => ['required', 'uuid', Rule::exists('custom_job_processes', 'id')->where('organization_id', TenantManager::getTenantId())],
                'configuration' => ['required', 'array:unit,amount,preparation_cost,included,ink_unit,ink_rate,ink_measurement_unit'],
                'configuration.unit' => ['required', Rule::in(['minute', 'impression', 'cm2', 'page', 'unit', 'job'])],
                'configuration.amount' => $money,
                'configuration.preparation_cost' => $money,
                'configuration.included' => ['present', 'array', 'max:5'],
                'configuration.included.*' => ['required', 'distinct', Rule::in(['operator', 'ink', 'machine', 'maintenance', 'electricity'])],
                'configuration.ink_unit' => ['required', Rule::in(['none', 'impression', 'cm2', 'page', 'measured'])],
                'configuration.ink_rate' => $money,
                'configuration.ink_measurement_unit' => ['nullable', 'required_if:configuration.ink_unit,measured', 'string', 'max:40'],
            ];
        }

        return CustomJobDecimals::numbers($request->validate($rules));
    }
}
