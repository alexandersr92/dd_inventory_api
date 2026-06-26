<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\TenantManager;
use Symfony\Component\HttpFoundation\Response;

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
