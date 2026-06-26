<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantNotificationSettingsController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Obtener el catálogo de notificaciones y la configuración actual del tenant.
     */
    public function index()
    {
        $this->authorize('viewAny', Setting::class);
        $orgId = Auth::user()->organization_id;

        // 1. Obtener eventos configurables a nivel de Tenant
        $events = NotificationEvent::where('scope', 'tenant')->get();

        // 2. Obtener configuraciones guardadas de este Tenant
        $settings = Setting::where('organization_id', $orgId)
            ->where('type', 'notification_preference')
            ->get()
            ->keyBy('key');

        // 3. Unir catálogo con configuración guardada (o fallbacks por defecto)
        $mappedSettings = $events->map(function ($event) use ($settings) {
            $saved = $settings->get($event->id);

            return [
                'key' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'value' => $saved ? $saved->value : 'enabled', // enabled por defecto
                'channels' => ($saved && isset($saved->options['channels'])) 
                    ? $saved->options['channels'] 
                    : $event->default_channels,
                'recipients' => ($saved && isset($saved->options['recipients']))
                    ? $saved->options['recipients']
                    : [
                        'user_ids' => [],
                        'emails' => []
                    ]
            ];
        });

        // 4. Obtener listado de usuarios de la organización activa para el selector del Frontend
        $tenantUsers = User::where('organization_id', $orgId)
            ->select('id', 'name', 'email')
            ->get();

        return response()->json([
            'settings' => $mappedSettings,
            'users' => $tenantUsers
        ], 200);
    }

    /**
     * Crear o actualizar la configuración de destinatarios y canales para un evento.
     */
    public function update(Request $request, string $key)
    {
        $this->authorize('create', Setting::class); // Reutilizamos permisos de creación de settings
        $orgId = Auth::user()->organization_id;

        // 1. Validar que la notificación exista y sea de ámbito Tenant
        $event = NotificationEvent::where('id', $key)
            ->where('scope', 'tenant')
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'Evento de notificación no encontrado o no configurable por el cliente.'
            ], 404);
        }

        // 2. Validar Request
        $request->validate([
            'value' => 'required|in:enabled,disabled',
            'channels' => 'required|array',
            'channels.*' => 'required|string|in:mail,database',
            'recipients' => 'nullable|array',
            'recipients.user_ids' => 'nullable|array',
            'recipients.user_ids.*' => 'required|string',
            'recipients.emails' => 'nullable|array',
            'recipients.emails.*' => 'required|email',
        ]);

        $userIds = $request->input('recipients.user_ids', []);

        // 3. Validar que los user_ids pertenezcan a la misma organización del tenant para seguridad
        if (!empty($userIds)) {
            $invalidUsersCount = User::whereIn('id', $userIds)
                ->where('organization_id', '!=', $orgId)
                ->count();

            if ($invalidUsersCount > 0) {
                return response()->json([
                    'message' => 'Uno o más destinatarios de usuario no pertenecen a tu organización.'
                ], 403);
            }
        }

        // 4. Guardar o Actualizar el setting de preferencia
        $setting = Setting::updateOrCreate(
            [
                'organization_id' => $orgId,
                'type' => 'notification_preference',
                'key' => $key
            ],
            [
                'value' => $request->value,
                'options' => [
                    'channels' => $request->channels,
                    'recipients' => [
                        'user_ids' => $userIds,
                        'emails' => $request->input('recipients.emails', [])
                    ]
                ]
            ]
        );

        return response()->json([
            'message' => 'Configuración de notificación guardada correctamente.',
            'setting' => [
                'key' => $setting->key,
                'value' => $setting->value,
                'options' => $setting->options
            ]
        ], 200);
    }
}
