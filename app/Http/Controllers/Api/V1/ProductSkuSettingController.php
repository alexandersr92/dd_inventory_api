<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SkuGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProductSkuSettingController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function show(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $orgId = Auth::user()->organization_id;
        $config = SkuGeneratorService::getOrCreateConfig($orgId);

        $preview = SkuGeneratorService::getPreview($config->pattern, $config->counter ?: 42);

        return response()->json([
            'enabled' => (bool)$config->enabled,
            'pattern' => $config->pattern,
            'counter' => (int)$config->counter,
            'preview_example' => $preview,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('update', Product::class);

        $request->validate([
            'enabled' => 'required|boolean',
            'pattern' => 'required|string|max:100',
        ]);

        $pattern = trim($request->input('pattern'));
        $validation = SkuGeneratorService::validatePattern($pattern);

        if (!$validation['valid']) {
            throw ValidationException::withMessages([
                'pattern' => $validation['error'],
            ]);
        }

        $orgId = Auth::user()->organization_id;
        $config = SkuGeneratorService::getOrCreateConfig($orgId);

        $config->enabled = (bool)$request->input('enabled');
        $config->pattern = $pattern;
        $config->save();

        $preview = SkuGeneratorService::getPreview($config->pattern, $config->counter ?: 42);

        return response()->json([
            'message' => 'Configuración de SKU actualizada correctamente.',
            'enabled' => (bool)$config->enabled,
            'pattern' => $config->pattern,
            'counter' => (int)$config->counter,
            'preview_example' => $preview,
        ]);
    }
}
