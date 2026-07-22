<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de confirmación de renovación con la factura de DipleBill adjunta (PDF).
 */
class SubscriptionInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public string $pdfBytes,
        public string $filename
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.system')
            ->with([
                'subject' => $this->subjectLine,
                'body' => $this->bodyHtml,
            ])
            ->attachData($this->pdfBytes, $this->filename, ['mime' => 'application/pdf']);
    }
}
