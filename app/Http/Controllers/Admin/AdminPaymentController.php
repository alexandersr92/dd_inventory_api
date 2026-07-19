<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentProvider;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPaymentController extends Controller
{
    /** Métodos de pago + cola de comprobantes pendientes. */
    public function index()
    {
        $providers = PaymentProvider::orderByDesc('is_default')->get();
        $pending = PaymentSubmission::with(['organization', 'plan', 'provider'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $recent = PaymentSubmission::with(['organization', 'plan'])
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('reviewed_at')
            ->limit(20)
            ->get();

        return view('admin.payments.index', compact('providers', 'pending', 'recent'));
    }

    // ---- CRUD de métodos de pago ----

    public function storeProvider(Request $request)
    {
        $data = $this->validatedProvider($request);
        PaymentProvider::create($data);
        AdminAudit::log('payment_provider.create', 'payment_provider', $data['name'], "Método '{$data['name']}' creado");

        return redirect()->route('admin.payments.index')->with('success', 'Método de pago creado.');
    }

    public function updateProvider(Request $request, $id)
    {
        $provider = PaymentProvider::findOrFail($id);
        $provider->update($this->validatedProvider($request));
        AdminAudit::log('payment_provider.update', 'payment_provider', $provider->name, "Método '{$provider->name}' actualizado");

        return redirect()->route('admin.payments.index')->with('success', 'Método de pago actualizado.');
    }

    public function toggleProvider($id)
    {
        $provider = PaymentProvider::findOrFail($id);
        $provider->update(['is_active' => !$provider->is_active]);
        $estado = $provider->is_active ? 'activado' : 'desactivado';
        AdminAudit::log('payment_provider.toggle', 'payment_provider', $provider->name, "Método '{$provider->name}' {$estado}");

        return redirect()->route('admin.payments.index')->with('success', "Método '{$provider->name}' {$estado}.");
    }

    public function destroyProvider($id)
    {
        $provider = PaymentProvider::findOrFail($id);
        $name = $provider->name;
        $provider->delete();
        AdminAudit::log('payment_provider.delete', 'payment_provider', $name, "Método '{$name}' eliminado");

        return redirect()->route('admin.payments.index')->with('success', 'Método eliminado.');
    }

    // ---- Validación de comprobantes ----

    /** Muestra el comprobante subido por el cliente (imagen o PDF). */
    public function viewReceipt($id)
    {
        $submission = PaymentSubmission::findOrFail($id);
        if (!$submission->receipt_path || !Storage::disk('local')->exists($submission->receipt_path)) {
            abort(404, 'Comprobante no encontrado.');
        }
        return Storage::disk('local')->response($submission->receipt_path);
    }

    /** Aprueba el comprobante: asigna el plan y renueva la licencia. */
    public function approveSubmission(Request $request, $id)
    {
        $submission = PaymentSubmission::findOrFail($id);

        if ($submission->status !== 'pending') {
            return redirect()->route('admin.payments.index')
                ->withErrors(['error' => 'Este comprobante ya fue revisado.']);
        }

        $organization = Organization::find($submission->organization_id);
        if (!$organization) {
            return redirect()->route('admin.payments.index')
                ->withErrors(['error' => 'La organización ya no existe.']);
        }

        // Si el comprobante trae un plan, se asigna y se extiende la licencia por
        // su duración. Reutiliza la misma lógica de asignación del panel.
        if ($submission->plan_id && ($plan = Plan::find($submission->plan_id))) {
            $baseDate = ($organization->license_expires_at && $organization->license_expires_at->isFuture())
                ? $organization->license_expires_at
                : now();
            $newExpiresAt = $baseDate->copy()->addMonths($plan->duration_months);

            $organization->licenses()->create([
                'type' => 'add',
                'days' => $plan->duration_months * 30,
                'previous_expires_at' => $organization->license_expires_at,
                'new_expires_at' => $newExpiresAt,
            ]);
            $organization->update([
                'plan_id' => $plan->id,
                'tenancy_type' => $plan->tenancy_type,
                'is_lifetime' => false,
                'license_expires_at' => $newExpiresAt,
            ]);
        }

        $submission->update([
            'status' => 'approved',
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(),
            'reviewed_at' => now(),
        ]);

        AdminAudit::log('payment.approve', 'organization', $organization->id,
            "Comprobante aprobado de {$organization->name}" . ($submission->plan_id ? " (plan renovado)" : ''));

        // Notificar al cliente y avisar internamente de la renovación.
        $planName = $submission->plan?->name;
        $expiresLabel = $organization->license_expires_at?->format('d/m/Y');
        AdminNotifier::notifyClient(
            $organization->user?->email ?? $organization->email,
            '✅ Tu renovación fue aprobada — DipleBill',
            '<h2>¡Renovación confirmada!</h2>'
                . '<p>Validamos tu comprobante y tu licencia quedó activa.</p>'
                . ($planName ? '<p><strong>Plan:</strong> ' . e($planName) . '</p>' : '')
                . ($expiresLabel ? '<p><strong>Válida hasta:</strong> ' . e($expiresLabel) . '</p>' : '')
                . '<p>¡Gracias por seguir con nosotros!</p>'
        );
        AdminNotifier::notifyRoot(
            'renewal',
            '🔁 Licencia renovada: ' . $organization->name,
            '<h2>Renovación aprobada</h2>'
                . '<p><strong>Organización:</strong> ' . e($organization->name) . '</p>'
                . ($planName ? '<p><strong>Plan:</strong> ' . e($planName) . '</p>' : '')
                . ($expiresLabel ? '<p><strong>Vence:</strong> ' . e($expiresLabel) . '</p>' : '')
        );

        return redirect()->route('admin.payments.index')
            ->with('success', "Comprobante aprobado. La licencia de {$organization->name} fue renovada.");
    }

    /** Rechaza el comprobante con un motivo. */
    public function rejectSubmission(Request $request, $id)
    {
        $request->validate(['admin_notes' => 'required|string|max:500']);

        $submission = PaymentSubmission::findOrFail($id);
        if ($submission->status !== 'pending') {
            return redirect()->route('admin.payments.index')
                ->withErrors(['error' => 'Este comprobante ya fue revisado.']);
        }

        $submission->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(),
            'reviewed_at' => now(),
        ]);

        AdminAudit::log('payment.reject', 'organization', $submission->organization_id, "Comprobante rechazado: {$request->admin_notes}");

        // Notificar al cliente con el motivo del rechazo.
        $organization = Organization::find($submission->organization_id);
        if ($organization) {
            AdminNotifier::notifyClient(
                $organization->user?->email ?? $organization->email,
                'Tu comprobante necesita revisión — DipleBill',
                '<h2>No pudimos validar tu comprobante</h2>'
                    . '<p><strong>Motivo:</strong> ' . nl2br(e($request->admin_notes)) . '</p>'
                    . '<p>Revisa los datos y vuelve a subir tu comprobante desde la sección <em>Licencia</em>.</p>'
            );
        }

        return redirect()->route('admin.payments.index')->with('success', 'Comprobante rechazado.');
    }

    private function validatedProvider(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string|max:50',
            'instructions' => 'nullable|string',
            'mode' => 'required|in:test,live',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'supports_receipt' => 'nullable|boolean',
        ]);
    }
}
