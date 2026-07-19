<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorReport;
use App\Services\AdminAudit;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminErrorReportController extends Controller
{
    /** Listado de reportes (abiertos primero). */
    public function index()
    {
        $reports = ErrorReport::orderByRaw("status = 'resolved'")
            ->orderByDesc('created_at')
            ->paginate(20);

        $openCount = ErrorReport::where('status', 'open')->count();

        return view('admin.error-reports.index', compact('reports', 'openCount'));
    }

    /** Muestra la captura adjunta (disco privado). */
    public function screenshot($id)
    {
        $report = ErrorReport::findOrFail($id);
        if (!$report->screenshot_path || !Storage::disk('local')->exists($report->screenshot_path)) {
            abort(404, 'Captura no encontrada.');
        }

        return Storage::disk('local')->response($report->screenshot_path);
    }

    /** Marca un reporte como resuelto. */
    public function resolve(Request $request, $id)
    {
        $report = ErrorReport::findOrFail($id);
        $report->update([
            'status'           => 'resolved',
            'resolution_notes' => $request->input('resolution_notes'),
            'resolved_by'      => Auth::guard('admin')->id(),
            'resolved_at'      => now(),
        ]);

        AdminAudit::log('error_report.resolve', 'error_report', $report->id, 'Reporte de error marcado como resuelto');

        // Avisar al usuario que reportó el problema (reportes de clientes).
        if ($report->reporter_email) {
            AdminNotifier::notifyClient(
                $report->reporter_email,
                '✅ Tu reporte fue resuelto — DipleBill',
                '<h2>¡Resolvimos tu reporte!</h2>'
                    . '<p>Hola ' . e($report->reporter_name ?? '') . ', el problema que nos reportaste ya fue atendido.</p>'
                    . '<p><strong>Tu reporte:</strong></p><p style="color:#6b7280;">' . nl2br(e($report->message)) . '</p>'
                    . ($report->resolution_notes
                        ? '<p><strong>Respuesta de soporte:</strong></p><p>' . nl2br(e($report->resolution_notes)) . '</p>'
                        : '')
                    . '<p>Gracias por ayudarnos a mejorar.</p>'
            );
        }

        return redirect()->route('admin.error-reports.index')->with('success', 'Reporte marcado como resuelto.');
    }

    /** Elimina un reporte y su captura. */
    public function destroy($id)
    {
        $report = ErrorReport::findOrFail($id);
        if ($report->screenshot_path && Storage::disk('local')->exists($report->screenshot_path)) {
            Storage::disk('local')->delete($report->screenshot_path);
        }
        $report->delete();

        AdminAudit::log('error_report.delete', 'error_report', $id, 'Reporte de error eliminado');

        return redirect()->route('admin.error-reports.index')->with('success', 'Reporte eliminado.');
    }
}
