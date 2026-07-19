<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErrorReport;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErrorReportController extends Controller
{
    /**
     * Un usuario del negocio (dueño/admin) reporta un problema desde su panel.
     * El reporte queda visible para el equipo root en /admin/error-reports.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'message'    => 'required|string|max:5000',
            'page_url'   => 'nullable|string|max:2000',
            'screenshot' => 'nullable|image|max:8192', // 8 MB
        ]);

        $user = Auth::user();
        $org = $user->organization;

        $path = null;
        if ($request->hasFile('screenshot')) {
            $path = $request->file('screenshot')->store('error_reports', 'local');
        }

        $report = ErrorReport::create([
            'source'            => 'tenant',
            'organization_id'   => $org?->id,
            'organization_name' => $org?->name,
            'reporter_name'     => $user->name,
            'reporter_email'    => $user->email,
            'message'           => $data['message'],
            'page_url'          => $data['page_url'] ?? null,
            'user_agent'        => $request->userAgent(),
            'screenshot_path'   => $path,
            'status'            => 'open',
        ]);

        AdminNotifier::notifyRoot(
            'error_report',
            '🐛 Reporte de error de ' . ($org?->name ?? 'un cliente'),
            '<h2>Reporte de error de un cliente</h2>'
                . '<p><strong>Organización:</strong> ' . e($org?->name ?? '—') . '</p>'
                . '<p><strong>Reportado por:</strong> ' . e($user->name) . ' (' . e($user->email) . ')</p>'
                . ($data['page_url'] ?? null ? '<p><strong>Página:</strong> ' . e($data['page_url']) . '</p>' : '')
                . '<p><strong>Mensaje:</strong></p><p>' . nl2br(e($data['message'])) . '</p>'
                . ($path ? '<p><em>Incluye una captura (disponible en el panel).</em></p>' : '')
        );

        return response()->json(['ok' => true, 'message' => 'Reporte enviado. ¡Gracias!'], 201);
    }
}
