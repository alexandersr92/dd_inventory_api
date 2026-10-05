<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantManager;
use App\Support\CustomJobs;
use Illuminate\Http\Request;

class CustomJobsAccessController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenant = TenantManager::getTenant();
        $names = $request->user()->roles()->where('organization_id', $tenant->id)
            ->with('permissions')->get()->flatMap->permissions->pluck('name');
        $capabilities = [];
        foreach (CustomJobs::PERMISSIONS as $name => $label) {
            $capabilities[$name] = $names->contains($name);
        }
        $capabilities['custom_job.annul_payment'] = $capabilities['custom_job.annul_payment'] && $capabilities['custom_job.cancel'];
        $dependencies = [];
        foreach (['products', 'inventories', 'purchases', 'invoices', 'credits'] as $slug) {
            $dependencies[$slug] = $tenant->modules()->where('slug', $slug)
                ->where('modules.status', 'active')->wherePivot('status', 'active')->exists();
        }

        return response()->json([
            'module' => CustomJobs::MODULE,
            'organization_id' => $tenant->id,
            'status' => 'active',
            'capabilities' => $capabilities,
            'dependencies' => $dependencies,
        ])->header('Cache-Control', 'no-store');
    }
}
