<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría de acciones sensibles del panel de super-admin.
 * Nunca lanza excepción: si falla el registro, no debe romper la acción.
 */
class AdminAudit
{
    public static function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $description = null
    ): void {
        try {
            $admin = Auth::guard('admin')->user();
            AdminAuditLog::create([
                'admin_id' => $admin?->id,
                'admin_name' => $admin?->name ?? $admin?->email,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'description' => $description,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
