<?php

namespace App\Traits;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

trait TenantQueueContext
{
    /**
     * ID de la organización (Tenant) para la ejecución en cola.
     *
     * @var string|null
     */
    public $organizationId;

    /**
     * Inicializar el contexto del tenant en el constructor del Job/Notification.
     */
    public function initializeTenantContext(?string $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Laravel llama automáticamente a __wakeup cuando el Job se despierta de la cola.
     * Esto reconectará de forma limpia la conexión de base de datos del inquilino.
     */
    public function __wakeup()
    {
        if ($this->organizationId) {
            $organization = Organization::find($this->organizationId);
            if ($organization && $organization->tenancy_type === 'dedicated' && $organization->db_database) {
                $dbName = $organization->db_database;

                if (config('database.connections.mysql.database') !== $dbName) {
                    config(['database.connections.mysql.database' => $dbName]);
                    DB::purge('mysql');
                    DB::reconnect('mysql');
                }
            }
        }
    }
}
