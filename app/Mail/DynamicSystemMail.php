<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Envío síncrono a propósito: los correos del sistema (avisos, comprobantes,
// pruebas de SMTP) deben salir en el momento, sin depender de un worker de cola.
class DynamicSystemMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $body;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $body)
    {
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject($this->subject)
                    ->view('emails.system')
                    ->with([
                        'subject' => $this->subject,
                        'body' => $this->body,
                    ]);
    }
}
