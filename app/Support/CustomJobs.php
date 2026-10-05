<?php

namespace App\Support;

use Illuminate\Http\Request;

final class CustomJobs
{
    public const MODULE = 'custom_jobs';

    public const ADMIN_ABILITY = 'admin:custom_jobs';

    public const PERMISSIONS = [
        'custom_job.index' => 'Consultar trabajos personalizados',
        'custom_job.costs' => 'Consultar costos de trabajos personalizados',
        'custom_job.quote' => 'Crear y editar cotizaciones de trabajos',
        'custom_job.order' => 'Crear y editar pedidos de trabajos',
        'custom_job.production' => 'Registrar progreso y consumo de trabajos',
        'custom_job.delivery' => 'Registrar entregas de trabajos',
        'custom_job.return' => 'Registrar devoluciones de trabajos',
        'custom_job.payment' => 'Registrar cobros de trabajos',
        'custom_job.cancel' => 'Cancelar pedidos de trabajos',
        'custom_job.annul_payment' => 'Anular cobros y cancelar pedidos de trabajos',
        'custom_job.configure' => 'Configurar trabajos personalizados',
    ];

    // Only credential-validated admin authentication routes issue this ability.
    // A request header or payload flag on a business operation cannot grant it.
    public static function tokenAbilities(Request $request): array
    {
        return $request->routeIs('api.admin.login', 'api.admin.register', 'api.admin.google')
            ? ['*', self::ADMIN_ABILITY] : ['*'];
    }
}
