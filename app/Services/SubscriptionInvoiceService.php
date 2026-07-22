<?php

namespace App\Services;

use App\Models\GlobalSetting;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Emite y renderiza las facturas de suscripción de DipleBill hacia el cliente.
 * La factura guarda un snapshot inmutable del emisor y del cliente, y el PDF se
 * genera bajo demanda desde ese snapshot (no depende de archivos en disco, así
 * sobrevive a redeploys del contenedor).
 */
class SubscriptionInvoiceService
{
    /** Datos del emisor (tu empresa), editables en Configuración Global, con valores por defecto. */
    public static function issuerData(): array
    {
        $get = fn (string $key, string $default = '') => GlobalSetting::where('key', $key)->value('value') ?: $default;

        return [
            'name'        => $get('company_legal_name', 'DipleBill'),
            'ruc'         => $get('company_ruc', ''),
            'address'     => $get('company_address', ''),
            'phone'       => $get('company_phone', ''),
            'email'       => $get('company_email', ''),
            'website'     => $get('company_website', ''),
        ];
    }

    /** Genera el siguiente número correlativo del año: DB-2026-0001. */
    public static function nextNumber(\DateTimeInterface $issuedAt): string
    {
        $year = (int) $issuedAt->format('Y');
        $prefix = "DB-{$year}-";

        $last = SubscriptionInvoice::where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Crea la factura a partir de un comprobante aprobado.
     * Idempotente: si ya existe factura para ese comprobante, la devuelve.
     */
    public static function createFromSubmission(
        PaymentSubmission $submission,
        Organization $organization,
        ?Plan $plan,
        ?\DateTimeInterface $periodStart,
        ?\DateTimeInterface $periodEnd,
        ?string $adminId
    ): SubscriptionInvoice {
        $existing = SubscriptionInvoice::where('payment_submission_id', $submission->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::connection('central')->transaction(function () use ($submission, $organization, $plan, $periodStart, $periodEnd, $adminId) {
            $issuedAt = now();

            $concept = $plan
                ? "Suscripción DipleBill — {$plan->name}"
                : 'Renovación de suscripción DipleBill';

            $customer = [
                'name'    => $organization->name,
                'contact' => $organization->user?->name ?? '',
                'email'   => $organization->user?->email ?? $organization->email ?? '',
                'phone'   => $organization->phone ?? '',
                'address' => $organization->address ?? '',
            ];

            return SubscriptionInvoice::create([
                'number'                => self::nextNumber($issuedAt),
                'organization_id'       => $organization->id,
                'payment_submission_id' => $submission->id,
                'plan_id'               => $plan?->id,
                'concept'               => $concept,
                'period_start'          => $periodStart,
                'period_end'            => $periodEnd,
                'amount'                => $submission->amount,
                'currency'              => $submission->currency ?? ($plan?->currency ?? 'NIO'),
                'payment_method'        => $submission->provider?->name ?? 'Transferencia',
                'reference'             => $submission->reference,
                'issuer'                => self::issuerData(),
                'customer'              => $customer,
                'issued_at'             => $issuedAt,
                'issued_by'             => $adminId,
            ]);
        });
    }

    /** Renderiza el PDF de la factura (bytes) desde su snapshot. */
    public static function renderPdf(SubscriptionInvoice $invoice): string
    {
        return Pdf::loadView('invoices.subscription', ['invoice' => $invoice])
            ->setPaper('letter')
            ->output();
    }

    /** Nombre de archivo sugerido para la descarga. */
    public static function filename(SubscriptionInvoice $invoice): string
    {
        return 'Factura-' . $invoice->number . '.pdf';
    }
}
