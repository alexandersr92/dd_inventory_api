<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobReports;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CustomJobReportController extends Controller
{
    public function index(Request $request, CustomJobReports $reports)
    {
        $data = $request->validate([
            'store_id' => ['required', 'uuid'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'confirmed', 'cancelled'])],
            'due_state' => ['nullable', Rule::in(['all', 'pending', 'overdue', 'completed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'delivery_from' => ['nullable', 'date_format:Y-m-d'],
            'delivery_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('delivery_from') ? ['after_or_equal:delivery_from'] : [])],
        ]);

        return response()->json($reports->index($request->user(), $data))->header('Cache-Control', 'no-store');
    }
}
