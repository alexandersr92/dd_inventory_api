@extends('reports.layout', ['title' => 'Reporte de Facturación (Invoices)'])

@section('content')
    <h3 class="section-title">Detalle de Facturas</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Folio / Fecha</th>
                <th>Cliente</th>
                <th>Tipo de Pago</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Vencimiento / Mora</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $invoice)
                <tr>
                    <td>
                        {{ $invoice['invoice_number'] ?? 'N/A' }}<br>
                        <small style="color: #666;">{{ substr($invoice['created_at'], 0, 10) }}</small>
                    </td>
                    <td>
                        {{ $invoice['client_name'] ?? 'N/A' }}
                    </td>
                    <td>{{ $invoice['payment_method'] ?? 'N/A' }}</td>
                    <td>{{ $currency }}{{ number_format($invoice['grand_total'] ?? 0, 2) }}</td>
                    <td>{{ ucfirst($invoice['invoice_status'] ?? 'N/A') }}</td>
                    <td>
                        Vence: N/A<br>
                        <small style="color: {{ ($invoice['dias_mora'] ?? 0) > 0 ? 'red' : 'green' }};">
                            Mora: {{ $invoice['dias_mora'] ?? 0 }} días
                        </small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No hay facturas en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
