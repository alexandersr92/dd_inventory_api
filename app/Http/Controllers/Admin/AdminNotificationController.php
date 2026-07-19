<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GlobalSetting;
use App\Services\AdminAudit;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    /** Toggles booleanos que administra esta pantalla (clave => por defecto). */
    private const TOGGLES = [
        'notify_new_account'   => true,
        'notify_expiring'      => true,
        'notify_renewal'       => true,
        'notify_payment'       => true,
        'notify_error_report'  => true,
        'notify_client_enabled' => true,
    ];

    public function index()
    {
        $settings = [];
        foreach (self::TOGGLES as $key => $default) {
            $val = GlobalSetting::where('key', $key)->value('value');
            $settings[$key] = $val === null ? $default : (bool) (int) $val;
        }
        $settings['notify_recipients'] = GlobalSetting::where('key', 'notify_recipients')->value('value') ?? '';

        $adminEmails = Admin::pluck('email')->filter()->values()->all();

        return view('admin.notifications', compact('settings', 'adminEmails'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'notify_recipients' => 'nullable|string|max:1000',
        ]);

        // Toggles: un checkbox no marcado no llega en el request -> se guarda 0.
        foreach (array_keys(self::TOGGLES) as $key) {
            GlobalSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $request->boolean($key) ? '1' : '0']
            );
        }

        GlobalSetting::updateOrCreate(
            ['key' => 'notify_recipients'],
            ['value' => trim($data['notify_recipients'] ?? '')]
        );

        AdminAudit::log('notifications.update', 'settings', 'notifications', 'Configuración de notificaciones actualizada');

        return redirect()->route('admin.notifications.index')->with('success', 'Configuración de notificaciones guardada.');
    }

    /** Envía un correo de prueba a los destinatarios root configurados. */
    public function test()
    {
        $recipients = AdminNotifier::rootRecipients();
        if (empty($recipients)) {
            return redirect()->route('admin.notifications.index')
                ->withErrors(['error' => 'No hay destinatarios válidos configurados.']);
        }

        $admin = Auth::guard('admin')->user();
        foreach ($recipients as $email) {
            \Illuminate\Support\Facades\Mail::to($email)->sendNow(
                new \App\Mail\DynamicSystemMail(
                    'Prueba de notificaciones — DipleBill',
                    '<h2>¡Funciona!</h2><p>Este es un correo de prueba enviado desde el panel de notificaciones por <strong>' . e($admin->name ?? 'admin') . '</strong>.</p><p>Si lo recibiste, las alertas del sistema llegarán a esta dirección.</p>'
                )
            );
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Correo de prueba enviado a: ' . implode(', ', $recipients));
    }
}
