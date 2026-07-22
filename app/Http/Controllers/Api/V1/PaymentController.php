<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    /** Métodos de pago activos que el cliente puede usar para renovar. */
    public function providers()
    {
        $providers = PaymentProvider::where('is_active', true)
            ->orderByDesc('is_default')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'driver' => $p->driver,
                'instructions' => $p->instructions,
                'supports_receipt' => $p->supports_receipt,
            ]);

        return response()->json(['data' => $providers]);
    }

    /** El cliente sube un comprobante de pago para validar su renovación. */
    public function submit(Request $request)
    {
        $request->validate([
            'plan_id' => 'nullable|uuid|exists:central.plans,id',
            'provider_id' => 'nullable|uuid|exists:central.payment_providers,id',
            'amount' => 'required|numeric|min:0',
            'reference' => 'nullable|string|max:255',
            'receipt' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $orgId = Auth::user()->organization_id;
        if (!$orgId) {
            return response()->json(['message' => 'No tienes una organización asociada.'], Response::HTTP_FORBIDDEN);
        }

        // Una sola submission pendiente por organización: evita el spam de archivos
        // de 5MB y de correos a root, y la confusión de comprobantes duplicados.
        if (PaymentSubmission::where('organization_id', $orgId)->where('status', 'pending')->exists()) {
            return response()->json([
                'message' => 'Ya tienes un comprobante pendiente de validación. Espera a que lo revisemos antes de enviar otro.',
            ], Response::HTTP_CONFLICT);
        }

        // Comprobantes en disco privado (no accesibles públicamente).
        $path = $request->file('receipt')->store("payment_receipts/{$orgId}", 'local');

        $plan = $request->plan_id ? Plan::find($request->plan_id) : null;

        $submission = PaymentSubmission::create([
            'organization_id' => $orgId,
            'plan_id' => $plan?->id,
            'provider_id' => $request->provider_id
                ?? PaymentProvider::where('is_default', true)->value('id'),
            'amount' => $request->amount,
            'currency' => $plan?->currency ?? 'NIO',
            'reference' => $request->reference,
            'receipt_path' => $path,
            'status' => 'pending',
        ]);

        // Avisar al equipo root que hay un comprobante por validar.
        $org = Auth::user()->organization;
        \App\Services\AdminNotifier::notifyRoot(
            'payment',
            '💳 Comprobante por validar: ' . ($org?->name ?? 'Organización'),
            '<h2>Nuevo comprobante de pago</h2>'
                . '<p><strong>Organización:</strong> ' . e($org?->name ?? '—') . '</p>'
                . '<p><strong>Plan:</strong> ' . e($plan?->name ?? 'Renovación') . '</p>'
                . '<p><strong>Monto:</strong> ' . number_format((float) $request->amount, 2) . ' ' . e($plan?->currency ?? 'NIO') . '</p>'
                . ($request->reference ? '<p><strong>Referencia:</strong> ' . e($request->reference) . '</p>' : '')
                . '<p>Valídalo en el panel: <em>Pagos → Comprobantes por validar</em>.</p>'
        );

        return response()->json([
            'message' => 'Comprobante recibido. Lo validaremos y activaremos tu renovación.',
            'data' => $this->present($submission),
        ], Response::HTTP_CREATED);
    }

    /** Historial de comprobantes de la organización del cliente. */
    public function mySubmissions()
    {
        $orgId = Auth::user()->organization_id;

        $submissions = PaymentSubmission::with('plan')
            ->where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => $this->present($s));

        return response()->json(['data' => $submissions]);
    }

    /** Descarga del comprobante propio (autenticado, solo de su organización). */
    public function downloadReceipt($id)
    {
        $orgId = Auth::user()->organization_id;
        $submission = PaymentSubmission::where('id', $id)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        if (!$submission->receipt_path || !Storage::disk('local')->exists($submission->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($submission->receipt_path);
    }

    private function present(PaymentSubmission $s): array
    {
        return [
            'id' => $s->id,
            'plan' => $s->plan?->name,
            'amount' => $s->amount,
            'currency' => $s->currency,
            'reference' => $s->reference,
            'status' => $s->status,
            'admin_notes' => $s->admin_notes,
            'created_at' => $s->created_at,
            'reviewed_at' => $s->reviewed_at,
        ];
    }
}
