<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Models\User;
use App\Models\NotificationEvent;
use App\Notifications\DynamicSystemNotification;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * Intercepta el evento lanzado y despacha la notificación a los destinatarios correctos.
     */
    public function handle($event): void
    {
        // 1. Verificar si el evento implementa la metadata necesaria para notificaciones
        if (!method_exists($event, 'toNotification')) {
            return;
        }

        $meta = $event->toNotification();
        $eventKey = $meta['event_key'] ?? null;
        $organizationId = $meta['organization_id'] ?? null;
        $data = $meta['data'] ?? [];

        if (!$eventKey) {
            return;
        }

        // 2. Obtener el evento del catálogo global para validar scope y canales por defecto
        $catalogEvent = NotificationEvent::find($eventKey);
        $defaultChannels = $catalogEvent ? $catalogEvent->default_channels : ['mail'];

        $channels = $defaultChannels;
        $recipientsUsers = collect();
        $recipientsEmails = collect();

        // 3. Evaluar ámbito: Global (Plataforma) o Tenant (Inquilino)
        if ($organizationId) {
            // Ámbito Tenant: Buscar configuración personalizada del cliente
            $setting = Setting::where('organization_id', $organizationId)
                ->where('type', 'notification_preference')
                ->where('key', $eventKey)
                ->first();

            // Si el inquilino deshabilitó explícitamente la notificación, no se envía nada
            if ($setting && $setting->value === 'disabled') {
                return;
            }

            if ($setting && is_array($setting->options)) {
                $options = $setting->options;
                
                // Canales personalizados
                if (!empty($options['channels'])) {
                    $channels = $options['channels'];
                }

                // Destinatarios personalizados
                if (!empty($options['recipients'])) {
                    $recipients = $options['recipients'];

                    // Usuarios del sistema internos (IDs)
                    if (!empty($recipients['user_ids'])) {
                        // Importante: al estar dentro del ciclo de la petición HTTP,
                        // la conexión por defecto ya está conmutada al tenant por el middleware
                        $recipientsUsers = User::whereIn('id', $recipients['user_ids'])->get();
                    }

                    // Correos electrónicos externos libres
                    if (!empty($recipients['emails'])) {
                        $recipientsEmails = collect($recipients['emails']);
                    }
                }
            }

            // Si el cliente no ha personalizado destinatarios, usamos los destinatarios por defecto del evento
            if ($recipientsUsers->isEmpty() && $recipientsEmails->isEmpty()) {
                $defaultNotifiables = collect($meta['notifiables'] ?? []);
                foreach ($defaultNotifiables as $notifiable) {
                    if ($notifiable instanceof User) {
                        $recipientsUsers->push($notifiable);
                    } elseif (is_string($notifiable) && filter_var($notifiable, FILTER_VALIDATE_EMAIL)) {
                        $recipientsEmails->push($notifiable);
                    }
                }
            }

        } else {
            // Ámbito Global: Usar destinatarios provistos directamente por el evento
            $defaultNotifiables = collect($meta['notifiables'] ?? []);
            foreach ($defaultNotifiables as $notifiable) {
                if ($notifiable instanceof User) {
                    $recipientsUsers->push($notifiable);
                } elseif (is_string($notifiable) && filter_var($notifiable, FILTER_VALIDATE_EMAIL)) {
                    $recipientsEmails->push($notifiable);
                }
            }
        }

        // 4. Despachar a usuarios internos (Models con trait Notifiable)
        if ($recipientsUsers->isNotEmpty()) {
            Notification::send(
                $recipientsUsers,
                new DynamicSystemNotification($eventKey, $data, $organizationId, $channels)
            );
        }

        // 5. Despachar a correos electrónicos externos adicionales (Anonymous Route)
        if ($recipientsEmails->isNotEmpty()) {
            foreach ($recipientsEmails as $email) {
                Notification::route('mail', $email)->notify(
                    new DynamicSystemNotification($eventKey, $data, $organizationId, $channels)
                );
            }
        }
    }
}
