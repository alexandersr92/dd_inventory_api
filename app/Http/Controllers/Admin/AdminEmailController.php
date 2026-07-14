<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\Services\MailConfigurator;
use App\Mail\DynamicSystemMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminEmailController extends Controller
{
    /**
     * Show email settings and templates.
     */
    public function index()
    {
        $smtp = [
            'mailer' => MailConfigurator::get('smtp_mailer', 'log'),
            'host' => MailConfigurator::get('smtp_host', '127.0.0.1'),
            'port' => MailConfigurator::get('smtp_port', '2525'),
            'username' => MailConfigurator::get('smtp_username', ''),
            'password' => MailConfigurator::get('smtp_password', '') ? '********' : '', // Mask for safety
            'encryption' => MailConfigurator::get('smtp_encryption', 'none'),
            'from_address' => MailConfigurator::get('smtp_from_address', 'hello@example.com'),
            'from_name' => MailConfigurator::get('smtp_from_name', 'DipleBill'),
        ];

        $templates = EmailTemplate::all();

        return view('admin.emails', compact('smtp', 'templates'));
    }

    /**
     * Update SMTP credentials.
     */
    public function updateSmtp(Request $request)
    {
        $request->validate([
            'smtp_mailer' => 'required|in:smtp,log',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|numeric',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'nullable|in:none,tls,ssl',
            'smtp_from_address' => 'required|email',
            'smtp_from_name' => 'required|string|max:255',
        ]);

        MailConfigurator::set('smtp_mailer', $request->smtp_mailer);
        MailConfigurator::set('smtp_host', $request->smtp_host);
        MailConfigurator::set('smtp_port', $request->smtp_port);
        MailConfigurator::set('smtp_username', $request->smtp_username);
        
        // Only update password if a new one is provided
        if (!empty($request->smtp_password) && $request->smtp_password !== '********') {
            MailConfigurator::set('smtp_password', $request->smtp_password);
        }
        
        MailConfigurator::set('smtp_encryption', $request->smtp_encryption ?: 'none');
        MailConfigurator::set('smtp_from_address', $request->smtp_from_address);
        MailConfigurator::set('smtp_from_name', $request->smtp_from_name);

        return redirect()->route('admin.emails.index')
            ->with('success', 'Configuración de servidor SMTP actualizada con éxito.');
    }

    /**
     * Send a test email to verify SMTP settings.
     */
    public function sendTestEmail(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            // Apply current dynamic SMTP configurations
            $configured = MailConfigurator::applyConfiguration();
            if (!$configured) {
                return redirect()->route('admin.emails.index')
                    ->withErrors(['error' => 'No se puede enviar correo de prueba. Verifica que el Host y Puerto estén configurados.']);
            }

            $subject = 'DipleBill - Correo de Prueba SMTP';
            $body = '<h3>¡Felicidades!</h3>
<p>Este es un correo electrónico de prueba enviado desde el configurador de DipleBill para confirmar que tu servidor de correos (SMTP) se encuentra correctamente enlazado y activo.</p>
<hr style="border: 0; border-top: 1px solid #f3f4f6; margin: 20px 0;">
<p style="font-size: 14px; color: #6b7280;"><strong>Detalles Técnicos:</strong><br>
Mailer: ' . MailConfigurator::get('smtp_mailer') . '<br>
Host: ' . MailConfigurator::get('smtp_host') . '<br>
Port: ' . MailConfigurator::get('smtp_port') . '<br>
Encryption: ' . MailConfigurator::get('smtp_encryption') . '<br>
Sender: ' . MailConfigurator::get('smtp_from_name') . ' <' . MailConfigurator::get('smtp_from_address') . '></p>';

            // Send using direct Mail facade with dynamic configuration active
            Mail::to($request->test_email)->send(new DynamicSystemMail($subject, $body));

            return redirect()->route('admin.emails.index')
                ->with('success', "Correo de prueba enviado con éxito a '{$request->test_email}'. Por favor, verifica tu bandeja de entrada.");
        } catch (\Exception $e) {
            Log::error("Fallo al enviar correo de prueba SMTP: " . $e->getMessage());
            return redirect()->route('admin.emails.index')
                ->withErrors(['error' => 'Error al conectar con el servidor SMTP: ' . $e->getMessage()]);
        }
    }

    /**
     * Get details of a single template for AJAX editing.
     */
    public function getTemplate($id)
    {
        $template = EmailTemplate::findOrFail($id);
        return response()->json($template);
    }

    /**
     * Update template details.
     */
    public function updateTemplate(Request $request, $id)
    {
        $template = EmailTemplate::findOrFail($id);

        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $template->update([
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return redirect()->route('admin.emails.index')
            ->with('success', "Plantilla '{$template->name}' actualizada correctamente.");
    }
}
