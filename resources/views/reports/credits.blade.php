@extends('reports.layout', ['title' => 'Reporte de Créditos y Cartera'])

@section('content')

    {{-- Resumen de Cartera --}}
    <h3 class="section-title">Resumen de Cartera</h3>
    <table class="data-table">
        <tr>
            <th>Monto Total Otorgado</th>
            <th>Monto Recuperado</th>
            <th>Cartera por Cobrar (Deuda)</th>
        </tr>
        <tr>
            <td>${{ number_format($items['resumen']['total_otorgado'], 2) }}</td>
            <td><span style="color: green;">${{ number_format($items['resumen']['total_recuperado'], 2) }}</span></td>
            <td><span style="color: red; font-weight: bold;">${{ number_format($items['resumen']['total_deuda'], 2) }}</span></td>
        </tr>
    </table>

    {{-- Antigüedad de Deuda --}}
    <h3 class="section-title">Antigüedad de Saldos (Cartera Vencida)</h3>
    <table class="data-table">
        <tr>
            <th>0 a 30 días</th>
            <th>31 a 60 días</th>
            <th>61 a 90 días</th>
            <th>Más de 90 días</th>
        </tr>
        <tr>
            <td>${{ number_format($items['tramos']['0_30'], 2) }}</td>
            <td>${{ number_format($items['tramos']['31_60'], 2) }}</td>
            <td style="color: darkorange;">${{ number_format($items['tramos']['61_90'], 2) }}</td>
            <td style="color: red; font-weight: bold;">${{ number_format($items['tramos']['mas_90'], 2) }}</td>
        </tr>
    </table>

    {{-- Detalle de Créditos --}}
    <h3 class="section-title">Detalle de Deudores</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Fecha Emisión / Días Antig.</th>
                <th>Crédito Otorgado</th>
                <th>Abonado</th>
                <th>Deuda Pendiente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items['detalle'] as $cred)
                <tr>
                    <td>
                        <strong>{{ $cred['cliente'] }}</strong><br>
                        <small style="color: #666;">Límite Crédito: N/A</small>
                    </td>
                    <td>
                        {{ $cred['fecha'] }}<br>
                        <small>Antigüedad: {{ $cred['dias_antiguedad'] }} días</small>
                    </td>
                    <td>${{ number_format($cred['total'], 2) }}</td>
                    <td style="color: green;">${{ number_format($cred['pagado'], 2) }}</td>
                    <td style="color: red; font-weight: bold;">${{ number_format($cred['deuda'], 2) }}</td>
                    <td>{{ ucfirst($cred['estado']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No hay créditos registrados en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

@endsection
