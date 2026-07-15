<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $name;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = 'Código de recuperación de contraseña - DipleBill';
        
        $body = "<p>Hola <strong>{$this->name}</strong>,</p>
        <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta de DipleBill. Utiliza el siguiente código de verificación de 6 dígitos para continuar con el proceso:</p>
        <div style='background-color: #f3f4f6; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0;'>
            <span style='font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #111827;'>{$this->code}</span>
        </div>
        <p style='color: #6b7280; font-size: 14px;'>Este código es válido por 60 minutos. Si tú no solicitaste este restablecimiento, puedes ignorar este mensaje de forma segura.</p>
        <p>Atentamente,<br>El equipo de DipleBill</p>";

        return $this->subject($subject)
                    ->view('emails.system')
                    ->with([
                        'subject' => $subject,
                        'body' => $body,
                    ]);
    }
}
