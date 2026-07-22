@php
    $cur = $invoice->currency === 'USD' ? 'US$' : ($invoice->currency === 'NIO' ? 'C$' : $invoice->currency . ' ');
    $money = fn ($n) => $cur . number_format((float) $n, 2);
    $issuer = $invoice->issuer ?? [];
    $customer = $invoice->customer ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .wrap { padding: 36px 44px; }
        .top { width: 100%; }
        .top td { vertical-align: top; }
        .brand { font-size: 24px; font-weight: bold; color: #4f46e5; }
        .brand small { display: block; font-size: 11px; color: #6b7280; font-weight: normal; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 20px; margin: 0; color: #111827; letter-spacing: 1px; }
        .doc-title .num { font-size: 13px; color: #4f46e5; font-weight: bold; margin-top: 4px; }
        .doc-title .date { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .muted { color: #6b7280; }
        .parties { width: 100%; margin-top: 28px; }
        .parties td { width: 50%; vertical-align: top; padding-right: 16px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; font-weight: bold; margin-bottom: 4px; }
        .party strong { font-size: 13px; color: #111827; }
        .party div { margin-top: 1px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 30px; }
        table.items th { background: #111827; color: #fff; text-align: left; padding: 9px 12px; font-size: 11px; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .period { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .totals { width: 100%; margin-top: 14px; }
        .totals td { padding: 4px 12px; }
        .totals .k { text-align: right; color: #6b7280; }
        .totals .v { text-align: right; width: 130px; }
        .totals .grand td { border-top: 2px solid #111827; font-size: 15px; font-weight: bold; color: #111827; padding-top: 10px; }
        .pay { margin-top: 30px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px 16px; font-size: 11px; }
        .pay .label { margin-bottom: 6px; }
        .paid { display: inline-block; margin-top: 6px; color: #059669; border: 1px solid #059669; border-radius: 4px; padding: 3px 10px; font-weight: bold; font-size: 12px; }
        .foot { margin-top: 40px; text-align: center; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="top">
        <tr>
            <td>
                <div class="brand">{{ $issuer['name'] ?? 'DipleBill' }}
                    <small>
                        @if(!empty($issuer['ruc'])) RUC: {{ $issuer['ruc'] }}<br>@endif
                        @if(!empty($issuer['address'])) {{ $issuer['address'] }}<br>@endif
                        @if(!empty($issuer['phone'])) Tel: {{ $issuer['phone'] }}@endif
                        @if(!empty($issuer['email'])) · {{ $issuer['email'] }}@endif
                    </small>
                </div>
            </td>
            <td class="doc-title">
                <h1>FACTURA</h1>
                <div class="num">N° {{ $invoice->number }}</div>
                <div class="date">Fecha de emisión: {{ $invoice->issued_at?->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td class="party">
                <div class="label">Facturado a</div>
                <strong>{{ $customer['name'] ?? '—' }}</strong>
                @if(!empty($customer['contact']))<div>{{ $customer['contact'] }}</div>@endif
                @if(!empty($customer['email']))<div class="muted">{{ $customer['email'] }}</div>@endif
                @if(!empty($customer['phone']))<div class="muted">Tel: {{ $customer['phone'] }}</div>@endif
                @if(!empty($customer['address']))<div class="muted">{{ $customer['address'] }}</div>@endif
            </td>
            <td class="party">
                <div class="label">Detalles</div>
                <div><span class="muted">Método de pago:</span> {{ $invoice->payment_method ?? 'Transferencia' }}</div>
                @if($invoice->reference)<div><span class="muted">Referencia:</span> {{ $invoice->reference }}</div>@endif
                <div><span class="muted">Moneda:</span> {{ $invoice->currency }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="r">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $invoice->concept }}
                    @if($invoice->period_start && $invoice->period_end)
                        <div class="period">Período de suscripción: {{ $invoice->period_start->format('d/m/Y') }} — {{ $invoice->period_end->format('d/m/Y') }}</div>
                    @endif
                </td>
                <td class="r">{{ $money($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="k">Subtotal</td>
            <td class="v">{{ $money($invoice->amount) }}</td>
        </tr>
        <tr class="grand">
            <td class="k">Total</td>
            <td class="v">{{ $money($invoice->amount) }}</td>
        </tr>
    </table>

    <div class="pay">
        <div class="label">Estado del pago</div>
        Pago recibido y validado por {{ $issuer['name'] ?? 'DipleBill' }}.
        <div><span class="paid">PAGADO</span></div>
    </div>

    <div class="foot">
        Gracias por confiar en {{ $issuer['name'] ?? 'DipleBill' }}.
        @if(!empty($issuer['website'])) {{ $issuer['website'] }} · @endif
        Este documento fue generado electrónicamente y es válido sin firma ni sello.
    </div>
</div>
</body>
</html>
