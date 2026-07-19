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
                        $supportMessage = GlobalSetting::where('key', 'license_support_message')->value('value') ?? 'Tu licencia ha expirado. Contacta a soporte.';
                        return response()->json([
                            'message' => 'Tu licencia de uso ha expirado.',
                            'error_code' => 'LICENSE_EXPIRED',
                            'support_message' => $supportMessage,
                            // Datos de pago para que el cliente pueda renovar sin salir de la app.
                            'payment_account' => GlobalSetting::where('key', 'payment_account')->value('value') ?? '',
                            'payment_whatsapp' => GlobalSetting::where('key', 'payment_whatsapp')->value('value') ?? '',
                        ], Response::HTTP_PAYMENT_REQUIRED);
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
