<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SkuRegenerationLog;
use App\Services\SkuGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductSkuRegenerationController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function preview(Request $request)
    {
        $this->authorize('update', Product::class);

        $orgId = Auth::user()->organization_id;
        $patternOverride = $request->input('pattern');

        $result = SkuGeneratorService::previewRegeneration($orgId, $patternOverride);

        return response()->json($result);
    }

    public function confirm(Request $request)
    {
        $this->authorize('update', Product::class);

        $request->validate([
            'preview_token' => 'required|string',
        ]);

        $orgId = Auth::user()->organization_id;
        $previewToken = $request->input('preview_token');
        $userId = Auth::id();

        $result = SkuGeneratorService::confirmRegeneration($orgId, $previewToken, $userId);

        return response()->json($result);
    }

    public function exportLog(Request $request, string $id)
    {
        $this->authorize('viewAny', Product::class);

        $orgId = Auth::user()->organization_id;
        $log = SkuRegenerationLog::where('organization_id', $orgId)->findOrFail($id);

        $csv = "ID,Nombre,SKU_Anterior,SKU_Nuevo\n";
        foreach ($log->sku_map as $item) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $item['id'],
                str_replace('"', '""', $item['name']),
                str_replace('"', '""', $item['old_sku'] ?? ''),
                str_replace('"', '""', $item['new_sku'])
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"regeneracion_sku_{$log->created_at->format('Ymd_His')}.csv\"",
        ]);
    }
}
