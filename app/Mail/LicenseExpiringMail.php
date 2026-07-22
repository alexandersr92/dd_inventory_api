<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LicenseExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public int $daysLeft,
        public string $expiresAt
    ) {
    }

    public function build()
    {
        $subject = $this->daysLeft <= 1
            ? 'Tu licencia de DipleBill vence mañana'
            : "Tu licencia de DipleBill vence en {$this->daysLeft} días";

        $body = "<p>Hola <strong>{$this->name}</strong>,</p>
        <p>Tu licencia de DipleBill vence el <strong>{$this->expiresAt}</strong> (en {$this->daysLeft} día(s)).</p>
        <p>Para no interrumpir tu facturación, renueva desde tu panel en la sección <strong>Licencia</strong>: ahí encontrarás los métodos de pago disponibles y podrás subir tu comprobante.</p>
        <p style='color: #6b7280; font-size: 14px;'>Al vencer, el acceso se pausa pero tus datos se conservan y vuelven a estar disponibles al renovar.</p>
        <p>Atentamente,<br>El equipo de DipleBill</p>";

        return $this->subject($subject)
            ->view('emails.system')
            ->with(['subject' => $subject, 'body' => $body]);
    }
}
