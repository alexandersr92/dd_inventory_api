<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Traits\TenantQueueContext;
use App\Models\EmailTemplate;
use App\Services\MailConfigurator;
use App\Mail\DynamicSystemMail;

class DynamicSystemNotification extends Notification implements ShouldQueue
{
    use Queueable, TenantQueueContext;

    /**
     * Llave identificadora de la notificación/plantilla.
     *
     * @var string
     */
    public $eventKey;

    /**
     * Variables dinámicas del evento para el cuerpo de la plantilla.
     *
     * @var array
     */
    public $data;

    /**
     * Canales de envío activos.
     *
     * @var array
     */
    public $channels;

    /**
     * Crear una nueva instancia de notificación.
     */
    public function __construct(string $eventKey, array $data, ?string $organizationId = null, array $channels = ['mail'])
    {
        $this->eventKey = $eventKey;
        $this->data = $data;
        $this->channels = $channels;
        $this->initializeTenantContext($organizationId);
    }

    /**
     * Canales a través de los cuales se enviará la notificación.
     */
    public function via($notifiable): array
    {
        return $this->channels;
    }

    /**
     * Construir la representación de correo electrónico de la notificación.
     */
    public function toMail($notifiable)
    {
        // 1. Cargar y aplicar configuración SMTP dinámica
        MailConfigurator::applyConfiguration();

        // 2. Buscar la plantilla de correo en la base de datos central
        $template = EmailTemplate::where('key', $this->eventKey)->first();
        if (!$template) {
            $subject = 'Notificación: ' . ucwords(str_replace(['.', '_'], ' ', $this->eventKey));
            $body = '<p>Se ha generado una notificación del sistema.</p>';
            if (!empty($this->data)) {
                $body .= '<p><strong>Detalles:</strong></p><ul>';
                foreach ($this->data as $key => $val) {
                    $body .= '<li><strong>' . e($key) . ':</strong> ' . e((string)$val) . '</li>';
                }
                $body .= '</ul>';
            }
        } else {
            $subject = $template->subject;
            $body = $template->body;

            // Reemplazar las variables dinámicas {variable}
            foreach ($this->data as $key => $val) {
                $placeholder = '{' . $key . '}';
                $subject = str_replace($placeholder, (string)$val, $subject);
                $body = str_replace($placeholder, (string)$val, $body);
            }
        }

        // Obtener el destinatario
        $recipientEmail = $notifiable->routeNotificationFor('mail', $this);
        if (!$recipientEmail && is_string($notifiable)) {
            $recipientEmail = $notifiable;
        }

        // Retornar la estructura Mailable dinámica
        return (new DynamicSystemMail($subject, $body))->to($recipientEmail);
    }

    /**
     * Obtener la representación de matriz de la notificación (para base de datos/telescope).
     */
    public function toArray($notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'data' => $this->data,
            'organization_id' => $this->organizationId,
        ];
    }
}
