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
        // validateToken debe pasar aunque la licencia esté vencida: si no, el
        // guard del frontend toma la sesión como inválida y expulsa al usuario a
        // /login, sin poder llegar a la pantalla de renovación. Devolver el perfil
        // no da acceso a features (cada una sigue bloqueada con su propio 402).
        'api/v1/validateToken',
        'api/v1/plans',
        'api/v1/plans/*',
        'api/v1/payments/*',
        'api/v1/subscription-invoices',
        'api/v1/subscription-invoices/*',
    ];

    /**
     * Rutas que un cajero necesita para CERRAR una caja abierta y cuadrar la
     * gaveta aunque la licencia del dueño haya vencido a mitad de turno. Si no,
     * el arqueo del día queda atrapado y el efectivo sin conciliar. No dan
     * acceso a vender ni abrir nuevas cajas: solo leer la sesión/config del
     * turno en curso y finalizarlo. Método => patrones de path (Request::is()).
     */
    private const SHIFT_CLOSE_EXEMPT_ROUTES = [
        'GET' => [
            'api/v1/cash-sessions/active',
            'api/v1/settings',
            'api/v1/expense-categories',
        ],
        'POST' => [
            'api/v1/cash-sessions/close',
        ],
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
                        if (!$request->is(...self::LICENSE_EXEMPT_ROUTES) && !$this->isShiftCloseExempt($request)) {
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

    /**
     * ¿La petición es una de las lecturas/acciones que un cajero necesita para
     * cerrar su turno, exentas del bloqueo por licencia vencida? El match es por
     * método + path para no exponer, p. ej., el POST/PUT de settings.
     */
    private function isShiftCloseExempt(Request $request): bool
    {
        $patterns = self::SHIFT_CLOSE_EXEMPT_ROUTES[$request->method()] ?? [];

        return $patterns !== [] && $request->is(...$patterns);
    }
}
