<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use App\Support\CustomJobs;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CustomJobsAccess
{
    public function handle(Request $request, Closure $next, string $permission = 'custom_job.index', string ...$dependencies): Response
    {
        $user = $request->user();
        $tenant = TenantManager::getTenant();
        $token = $user?->currentAccessToken();
        // Explicit ability: Sanctum wildcard tokens (legacy/POS) do not qualify.
        if (! $token instanceof PersonalAccessToken ||
            ! in_array(CustomJobs::ADMIN_ABILITY, $token->abilities ?? [], true)) {
            return response()->json(['message' => 'Este módulo requiere una sesión del admin. Vuelve a iniciar sesión en el admin.', 'code' => 'CUSTOM_JOBS_ADMIN_SESSION_REQUIRED'], 403);
        }
        if (! $tenant || $tenant->id !== $user->organization_id ||
            ! $tenant->modules()->where('slug', CustomJobs::MODULE)
                ->where('modules.status', 'active')
                ->wherePivot('status', 'active')->exists()) {
            return response()->json(['message' => 'Trabajos personalizados no está activo en esta cuenta.', 'code' => 'CUSTOM_JOBS_INACTIVE'], 403);
        }
        // Read fresh tenant roles: neither a foreign role nor an Owner label bypasses authorization.
        $names = $user->roles()->where('organization_id', $tenant->id)
            ->with('permissions')->get()->flatMap->permissions->pluck('name');
        // Laravel may deduplicate this middleware class when nested groups repeat it.
        // Every effective guard therefore checks index plus all explicit permissions in one call.
        $extraPermissions = array_values(array_filter($dependencies, fn ($name) => array_key_exists($name, CustomJobs::PERMISSIONS)));
        $dependencies = array_values(array_diff($dependencies, $extraPermissions));
        $required = ['custom_job.index', $permission, ...$extraPermissions];
        if ($permission === 'custom_job.annul_payment') {
            $required[] = 'custom_job.cancel';
        }
        foreach ($required as $name) {
            if (! array_key_exists($name, CustomJobs::PERMISSIONS) || ! $names->contains($name)) {
                return response()->json(['message' => 'No tienes permiso para esta acción de trabajos personalizados.', 'code' => 'CUSTOM_JOBS_FORBIDDEN'], 403);
            }
        }

        foreach ($dependencies as $slug) {
            if (! in_array($slug, ['products', 'inventories', 'purchases', 'invoices', 'credits'], true) ||
                ! $tenant->modules()->where('slug', $slug)->where('modules.status', 'active')
                    ->wherePivot('status', 'active')->exists()) {
                return response()->json([
                    'message' => 'La acción requiere otro módulo activo en esta cuenta.',
                    'code' => 'CUSTOM_JOBS_DEPENDENCY_INACTIVE',
                    'module' => $slug,
                ], 403);
            }
        }

        return $next($request);
    }
}
