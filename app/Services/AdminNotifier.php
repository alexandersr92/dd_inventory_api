<?php

namespace App\Services;

use App\Mail\DynamicSystemMail;
use App\Models\Admin;
use App\Models\GlobalSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Punto central de notificaciones por correo del panel super-admin.
 *
 * Respeta los toggles configurados en /admin/notifications (persistidos en
 * global_settings) y envía de forma INMEDIATA (sendNow) para no depender de un
 * worker de cola: son alertas transaccionales (pago recibido, cuenta nueva…).
 */
class AdminNotifier
{
    /** Evento -> clave del toggle en global_settings. */
    public const EVENTS = [
        'new_account' => 'notify_new_account',
        'expiring'    => 'notify_expiring',
        'renewal'     => 'notify_renewal',
        'payment'     => 'notify_payment',
        'error_report' => 'notify_error_report',
    ];

    /** ¿Está habilitado el aviso a root para este evento? (por defecto: sí) */
    public static function enabled(string $event): bool
    {
        $key = self::EVENTS[$event] ?? null;
        if (!$key) {
            return false;
        }
        $val = GlobalSetting::where('key', $key)->value('value');

        return $val === null ? true : (bool) (int) $val;
    }

    /** ¿Se avisa por correo a los clientes (aprobación/rechazo/bienvenida)? */
    public static function clientEnabled(): bool
    {
        $val = GlobalSetting::where('key', 'notify_client_enabled')->value('value');

        return $val === null ? true : (bool) (int) $val;
    }

    /**
     * Destinatarios root. Si no hay lista configurada, cae a todos los admins.
     *
     * @return string[]
     */
    public static function rootRecipients(): array
    {
        $raw = GlobalSetting::where('key', 'notify_recipients')->value('value');
        $emails = collect(preg_split('/[,\s;]+/', (string) $raw))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            $emails = Admin::pluck('email')
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->all();
        }

        return $emails;
    }

    /** Notifica a los root (si el evento está habilitado). */
    public static function notifyRoot(string $event, string $subject, string $bodyHtml): void
    {
        if (!self::enabled($event)) {
            return;
        }
        foreach (self::rootRecipients() as $email) {
            self::deliver($email, $subject, $bodyHtml, 'root');
        }
    }

    /** Notifica a un cliente concreto (si los avisos a cliente están habilitados). */
    public static function notifyClient(?string $email, string $subject, string $bodyHtml): void
    {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !self::clientEnabled()) {
            return;
        }
        self::deliver($email, $subject, $bodyHtml, 'client');
    }

    /** Envío inmediato y tolerante a fallos: un correo caído nunca rompe el flujo. */
    private static function deliver(string $email, string $subject, string $bodyHtml, string $audience): void
    {
        try {
            // Usar el SMTP configurado en el panel (no el MAIL_* del .env, que por
            // defecto es 'log'). Si no hay SMTP válido, cae a 'log' sin romper.
            MailConfigurator::applyConfiguration();
            Mail::to($email)->sendNow(new DynamicSystemMail($subject, $bodyHtml));
        } catch (\Throwable $e) {
            Log::warning("AdminNotifier[$audience] falló para {$email}: " . $e->getMessage());
        }
    }
}
