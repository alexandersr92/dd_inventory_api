<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentProvider;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Services\AdminAudit;
use App\Services\AdminNotifier;
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

        // Para asignar plan al aprobar comprobantes que no lo traen.
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        return view('admin.payments.index', compact('providers', 'pending', 'recent', 'plans'));
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

    /** Descarga la factura de suscripción emitida para un comprobante aprobado. */
    public function downloadInvoice($id)
    {
        $invoice = \App\Models\SubscriptionInvoice::where('payment_submission_id', $id)->firstOrFail();

        return response(\App\Services\SubscriptionInvoiceService::renderPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . \App\Services\SubscriptionInvoiceService::filename($invoice) . '"',
        ]);
    }

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

        // Resolver el plan a aplicar: el del comprobante, o el que el admin
        // seleccione en el formulario si el comprobante no traía uno. SIN plan no
        // se puede extender la licencia, así que no se permite aprobar: antes se
        // marcaba "aprobado/renovado" sin extender ni un día (mensaje engañoso).
        $planId = $submission->plan_id ?: $request->input('plan_id');
        $plan = $planId ? Plan::find($planId) : null;

        if (!$plan) {
            return redirect()->route('admin.payments.index')
                ->withErrors(['error' => 'Este comprobante no tiene un plan asociado. Selecciona el plan a aplicar antes de aprobar (sin plan no se puede extender la licencia).']);
        }

        // Sección crítica bajo lock: re-verificar que el comprobante sigue
        // 'pending' y extender la licencia UNA sola vez. Sin este lock, dos
        // aprobaciones concurrentes (o un doble clic) extenderían 2x la licencia.
        $periodStart = null;
        $periodEnd = null;
        $race = false;

        \Illuminate\Support\Facades\DB::connection('central')->transaction(function () use ($id, $plan, $request, &$submission, &$organization, &$periodStart, &$periodEnd, &$race) {
            $locked = PaymentSubmission::where('id', $id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'pending') {
                $race = true;
                return;
            }

            // Releer la organización bloqueada para calcular la nueva expiración
            // sobre el valor vigente (no uno stale).
            $organization = Organization::where('id', $organization->id)->lockForUpdate()->first();

            $baseDate = ($organization->license_expires_at && $organization->license_expires_at->isFuture())
                ? $organization->license_expires_at
                : now();
            $newExpiresAt = $baseDate->copy()->addMonths($plan->duration_months);
            $periodStart = $baseDate;
            $periodEnd = $newExpiresAt;

            $organization->licenses()->create([
                'type' => 'add',
                // Días reales agregados (addMonths no siempre son 30): registrar el
                // diff exacto en vez de meses*30, que descuadraba el historial.
                'days' => (int) $baseDate->diffInDays($newExpiresAt),
                'previous_expires_at' => $organization->license_expires_at,
                'new_expires_at' => $newExpiresAt,
            ]);
            $organization->update([
                'plan_id' => $plan->id,
                'tenancy_type' => $plan->tenancy_type,
                'is_lifetime' => false,
                'license_expires_at' => $newExpiresAt,
            ]);

            $locked->update([
                'status' => 'approved',
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(),
                'reviewed_at' => now(),
                // Persistir el plan resuelto si el comprobante no lo traía.
                'plan_id' => $locked->plan_id ?: $plan->id,
            ]);

            $submission = $locked;
        });

        if ($race) {
            return redirect()->route('admin.payments.index')
                ->withErrors(['error' => 'Este comprobante ya fue revisado.']);
        }

        // Emitir la factura de DipleBill hacia el cliente (PDF descargable). No debe
        // romper la aprobación si algo falla.
        $invoice = null;
        try {
            $submission->loadMissing('provider');
            $invoice = \App\Services\SubscriptionInvoiceService::createFromSubmission(
                $submission,
                $organization,
                $plan,
                $periodStart,
                $periodEnd,
                \Illuminate\Support\Facades\Auth::guard('admin')->id()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo emitir la factura de suscripción: ' . $e->getMessage());
        }

        AdminAudit::log('payment.approve', 'organization', $organization->id,
            "Comprobante aprobado de {$organization->name}" . ($submission->plan_id ? " (plan renovado)" : ''));

        // Notificar al cliente (con la factura PDF adjunta) y avisar internamente.
        $planName = $plan->name;
        $expiresLabel = $organization->license_expires_at?->format('d/m/Y');
        $clientEmail = $organization->user?->email ?? $organization->email;
        $bodyHtml = '<h2>¡Renovación confirmada!</h2>'
            . '<p>Validamos tu comprobante y tu licencia quedó activa.</p>'
            . ($planName ? '<p><strong>Plan:</strong> ' . e($planName) . '</p>' : '')
            . ($expiresLabel ? '<p><strong>Válida hasta:</strong> ' . e($expiresLabel) . '</p>' : '')
            . ($invoice ? '<p>Adjuntamos tu factura <strong>N° ' . e($invoice->number) . '</strong>. También puedes descargarla desde tu panel, en la sección <em>Licencia</em>.</p>' : '')
            . '<p>¡Gracias por seguir con nosotros!</p>';

        if ($clientEmail && AdminNotifier::clientEnabled()) {
            try {
                if ($invoice) {
                    \Illuminate\Support\Facades\Mail::to($clientEmail)->sendNow(new \App\Mail\SubscriptionInvoiceMail(
                        '✅ Tu factura de DipleBill (' . $invoice->number . ')',
                        $bodyHtml,
                        \App\Services\SubscriptionInvoiceService::renderPdf($invoice),
                        \App\Services\SubscriptionInvoiceService::filename($invoice)
                    ));
                } else {
                    AdminNotifier::notifyClient($clientEmail, '✅ Tu renovación fue aprobada — DipleBill', $bodyHtml);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo enviar la factura al cliente: ' . $e->getMessage());
            }
        }
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
