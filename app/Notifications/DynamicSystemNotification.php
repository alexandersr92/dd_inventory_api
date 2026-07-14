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
            
            // Traducción del asunto por defecto
            if ($this->eventKey === 'tenant.invoice_created') {
                $subject = 'Nueva Factura Registrada';
            } elseif ($this->eventKey === 'tenant.credit_created') {
                $subject = 'Nuevo Crédito Registrado';
            }

            $body = '<p>Se ha generado una notificación del sistema.</p>';
            if (!empty($this->data)) {
                $body .= '<p><strong>Detalles:</strong></p><ul>';
                
                $translations = [
                    'invoice_number' => 'Número de Factura',
                    'client_name' => 'Cliente',
                    'grand_total' => 'Total Facturado',
                    'payment_method' => 'Método de Pago',
                    'invoice_type' => 'Tipo de Venta',
                    'store_name' => 'Sucursal / Tienda',
                    'invoice_date' => 'Fecha de Emisión',
                    'total_items' => 'Artículos Totales',
                    'discount' => 'Descuento',
                    'tax' => 'Impuesto (IVA)',
                    'total' => 'Total Neto',
                    'debt' => 'Saldo Pendiente',
                    'created_at' => 'Fecha de Creación',
                ];

                $valueTranslations = [
                    'cash' => 'Efectivo',
                    'credit' => 'Crédito',
                    'transfer' => 'Transferencia Bancaria',
                    'credit_card' => 'Tarjeta de Crédito',
                    'bacs' => 'Transferencia',
                ];

                $currency = $this->data['store_currency'] ?? '';

                foreach ($this->data as $key => $val) {
                    // Evitar pintar la variable de moneda como un elemento de lista independiente
                    if ($key === 'store_currency') {
                        continue;
                    }

                    $label = $translations[$key] ?? ucwords(str_replace('_', ' ', $key));
                    
                    // Traducir valores específicos si son de tipo string
                    $displayVal = (is_string($val) && isset($valueTranslations[strtolower($val)]))
                        ? $valueTranslations[strtolower($val)]
                        : $val;

                    // Formatear montos numéricos de dinero con la moneda de la tienda en lugar del signo $
                    if (in_array($key, ['grand_total', 'total', 'debt', 'tax', 'discount']) && is_numeric($displayVal)) {
                        $formattedNum = number_format((float)$displayVal, 2);
                        $displayVal = $currency ? $currency . ' ' . $formattedNum : $formattedNum;
                    }

                    $body .= '<li><strong>' . e($label) . ':</strong> ' . e((string)$displayVal) . '</li>';
                }
                $body .= '</ul>';
            }
        } else {
            $subject = $template->subject;
            $body = $template->body;

            // Reemplazar las variables dinámicas {variable}
            foreach ($this->data as $key => $val) {
                $placeholder = '{' . $key . '}';
                // Si la variable a reemplazar es un monto de dinero, formatearla adecuadamente
                if (in_array($key, ['grand_total', 'total', 'debt', 'tax', 'discount']) && is_numeric($val)) {
                    $currency = $this->data['store_currency'] ?? '';
                    $formattedNum = number_format((float)$val, 2);
                    $val = $currency ? $currency . ' ' . $formattedNum : $formattedNum;
                }
                $subject = str_replace($placeholder, (string)$val, $subject);
                $body = str_replace($placeholder, (string)$val, $body);
            }
        }

        // Obtener el destinatario
        $recipientEmail = null;
        if (is_string($notifiable)) {
            $recipientEmail = $notifiable;
        } elseif (method_exists($notifiable, 'routeNotificationFor')) {
            $recipientEmail = $notifiable->routeNotificationFor('mail', $this);
        }
        if (!$recipientEmail) {
            $recipientEmail = $notifiable->email ?? null;
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
