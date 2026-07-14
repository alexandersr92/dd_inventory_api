<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantManager;
use Symfony\Component\HttpFoundation\Response;

class TenantModuleAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $tenant = TenantManager::getTenant();

        if ($tenant) {
            // Check if organization has this module active
            $hasAccess = $tenant->modules()
                ->where('slug', $moduleSlug)
                ->where('organization_modules.status', 'active')
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'message' => "Your organization does not have access to the '{$moduleSlug}' module. Please upgrade your subscription."
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
