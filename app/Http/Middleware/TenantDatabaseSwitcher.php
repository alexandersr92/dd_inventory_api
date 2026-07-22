<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\TenantManager;
use Symfony\Component\HttpFoundation\Response;
use App\Models\GlobalSetting;

class TenantDatabaseSwitcher
{
    /**
     * Rutas de renovación que siguen accesibles con la licencia vencida, para
     * que el cliente pueda pagar y salir del bloqueo. Patrones de path (sin
     * dominio); ver Illuminate\Http\Request::is().
     */
    private const LICENSE_EXEMPT_ROUTES = [
        'api/v1/plans',
        'api/v1/plans/*',
        'api/v1/payments/*',
        'api/v1/subscription-invoices',
        'api/v1/subscription-invoices/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->organization_id) {
            // Since User model has connection = central, this relation loads from the central DB
            $organization = $user->organization;

            if ($organization) {
                if ($organization->status !== 'active') {
                    return response()->json([
                        'message' => 'La organización está inactiva o suspendida.'
                    ], Response::HTTP_FORBIDDEN);
                }

                if (!$organization->is_lifetime) {
                    if (!$organization->license_expires_at || $organization->license_expires_at < now()) {
                        // Un cliente con licencia vencida DEBE poder renovar: ver
                        // los métodos de pago, subir su comprobante y descargar su
                        // factura. Si bloqueáramos estas rutas aquí quedaría
                        // atrapado sin forma de pagar. El resto sí se bloquea.
                        if (!$request->is(...self::LICENSE_EXEMPT_ROUTES)) {
                            $supportMessage = GlobalSetting::where('key', 'license_support_message')->value('value') ?? 'Tu licencia ha expirado. Contacta a soporte.';
                            return response()->json([
                                'message' => 'Tu licencia de uso ha expirado.',
                                'error_code' => 'LICENSE_EXPIRED',
                                'support_message' => $supportMessage,
                            ], Response::HTTP_PAYMENT_REQUIRED);
                        }
                    }
                }

                TenantManager::setTenant($organization);

                if ($organization->tenancy_type === 'dedicated' && $organization->db_database) {
                    $dbName = $organization->db_database;

                    if (config('database.connections.mysql.database') !== $dbName) {
                        config(['database.connections.mysql.database' => $dbName]);
                        DB::purge('mysql');
                        DB::reconnect('mysql');
                    }
                }
            }
        }

        return $next($request);
    }
}
