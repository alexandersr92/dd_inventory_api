<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomJobResourcePreview;
use App\Support\CustomJobDecimals;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomJobResourcePreviewController extends Controller
{
    public function __invoke(Request $request, CustomJobResourcePreview $preview)
    {
        $request->replace(CustomJobDecimals::expand($request->all()));
        $input = $request->validate([
            'rate_id' => ['nullable', 'uuid'], 'machine_id' => ['nullable', 'uuid'],
            'rate_quantity' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,6'],
            'machine_minutes' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,6'],
            'ink_quantity' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,6'],
            'people' => ['present', 'array', 'max:50'],
            'people.*' => ['array:count,method,scope,amount,minutes'],
            'people.*.count' => ['required', 'integer', 'min:1', 'max:1000'],
            'people.*.method' => ['required', Rule::in(['hour', 'work'])],
            'people.*.scope' => ['required', Rule::in(['person', 'team'])],
            'people.*.amount' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,8'],
            'people.*.minutes' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,6'],
        ]);
        $input = array_merge(['rate_id' => null, 'machine_id' => null], CustomJobDecimals::numbers($input));
        foreach ($input['people'] as $i => $group) {
            if ($group['method'] === 'hour' && $group['scope'] !== 'person') {
                throw ValidationException::withMessages(["people.$i.scope" => 'El pago por hora es por persona.']);
            }
        }

        return response()->json(['data' => $preview->calculate($input)])->header('Cache-Control', 'no-store');
    }
}
